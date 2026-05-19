<?php

namespace App\Modules\Loyalty\Jobs;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\BillItem;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Models\BillLog;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Modules\Loyalty\Contracts\FraudServiceInterface;
use App\Modules\Loyalty\Contracts\PointsServiceInterface;
use App\Modules\Loyalty\Services\Transformers\BillDataMapper;
use App\Modules\Loyalty\Services\OCR\OCRProviderRegistry;
use App\Modules\Loyalty\Services\Fraud\Engine\FraudDetectionEngine;
use App\Modules\Loyalty\Services\Fraud\FraudAuditService;
use App\Modules\Loyalty\Services\LineItems\LineItemService;


class ProcessBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 120;

    public function __construct(public $billId) {}

    public function handle(
        OCRProviderRegistry $registry,
        FraudServiceInterface $fraud,
        PointsServiceInterface $points,
        BillDataMapper $mapper,
        FraudDetectionEngine $fraudEngine,
        FraudAuditService $fraudAudit,
        LineItemService $lineItemService,
    ) {

        Log::info('JOB START', ['bill_id' => $this->billId]);

        $start = microtime(true);

        $this->log($this->billId, 'processing_started');

        $bill = Bill::find($this->billId);

        if (!$bill) {
            $this->log($this->billId, 'bill_not_found');
            return;
        }

        if ($bill->status !== Bill::STATUS_PENDING) {
            $this->log($bill->id, 'skipped', 'status_not_pending');
            return;
        }

        Log::info('BILL FETCHED', ['bill' => $bill]);

        try {

            // -------------------------
            // ✅ STEP 1: OCR (OUTSIDE TRANSACTION)
            // -------------------------
            $chain = $registry->getChain();
            $raw = null;
            $lastError = null;

            Log::info('START OCR');

            foreach ($chain as $provider) {
                try {
                    $raw = $provider->extractText($bill->file_url);
                    break; // success, stop trying
                } catch (\Throwable $e) {
                    $lastError = $e;
                    Log::warning('OCR provider failed, trying next', ['error' => $e->getMessage()]);
                }
            }

            if ($raw === null) {
                $this->log($bill->id, 'ocr_failed', $lastError?->getMessage());
                $bill->markFailed();
                return; // all providers exhausted
            }

            $mapped = $mapper->map($raw);

            Log::info('OCR PROVIDER USED', [
                'bill_id' => $bill->id,
                'provider' => $mapped['provider']
            ]);

            Log::info('MAPPER OUTPUT', ['bill_id' => $bill->id, 'data' => $mapped]);

            $normalized = $mapped['normalized'] ?? null;

            if (!$normalized) {
                Log::error('Mapper failed: normalized missing', ['bill_id' => $bill->id]);
                $this->log($bill->id, 'validation_failed', 'Normalized data missing');
                $bill->markFailed();
                return;
            }

            if (!($normalized['is_document'] ?? true)) {
                Log::error('Uploaded file is not a bill/receipt', ['bill_id' => $bill->id]);
                $this->log($bill->id, 'validation_failed', 'Not a valid document');
                $bill->markInvalid();
                return;
            }

            Log::info('OCR DONE', [
                'bill_id' => $bill->id,
                'amount' => $normalized['amount'],
                'vendor' => $normalized['vendor']
            ]);

            $this->log($bill->id, 'ocr_success', [
                'amount' => $normalized['amount'],
                'vendor' => $normalized['vendor'],
            ]);

            // ✅ Validation
            if (empty($normalized['amount'])) {
                Log::error('Invalid OCR: missing amount', ['bill_id' => $bill->id]);
                $this->log($bill->id, 'validation_failed', 'Missing amount');
                $bill->markFailed();
                return;
            }

            // -------------------------
            // ✅ STEP 2: SEMANTIC HASH (IMPORTANT)
            // -------------------------
            $semanticHash = $this->generateSemanticHash($normalized);

            $pointsEarned = $points->calculate((float) $normalized['amount']);

            // -------------------------
            // ✅ STEP 3: CRITICAL DB SECTION
            // -------------------------
            $completed = false;
            DB::transaction(function () use ($bill, $mapped, $normalized, $pointsEarned, $semanticHash, $fraudEngine, $fraudAudit, $lineItemService, $mapper, &$completed) {

                $bill = Bill::where('id', $bill->id)
                    ->lockForUpdate()
                    ->first();

                if (!$bill || $bill->status !== Bill::STATUS_PENDING) {
                    $this->log($this->billId, 'skipped_in_transaction');
                    return;
                }

                // -------------------------
                // ✅ DUPLICATE CHECKS
                // -------------------------

                // OCR duplicate
                // if ($mapped['duplicate']) {
                //     Log::warning('Duplicate detected (OCR)', ['bill_id' => $bill->id]);
                //     $bill->markDuplicate();
                //     return;
                // }

                $ocrDuplicate = $mapped['duplicate'] ?? false;

                if ($ocrDuplicate) {
                    $this->log($bill->id, 'ocr_duplicate_flagged');
                }

                // Semantic duplicate
                if (Bill::where('semantic_hash', $semanticHash)->where('id', '!=', $bill->id)->exists()) {
                    Log::warning('Duplicate detected (Semantic Hash)', ['bill_id' => $bill->id]);
                    $this->log($bill->id, 'duplicate_detected', 'semantic_hash');
                    $bill->markDuplicate();
                    return;
                }

                $payload = $normalized ?? [];
                $payload['is_duplicate'] = $ocrDuplicate;

                $result = $fraudEngine->analyze($payload, $bill);

                $decision = $fraudAudit->handle($bill, $result);

                $this->log($bill->id, 'fraud_scored', [
                    'score' => $result->score,
                    'decision' => $decision,
                ]);

                if ($decision === 'rejected') {
                    Log::warning('Fraud rejected', [
                        'bill_id' => $bill->id,
                        'score' => $result->score,
                        'reasons' => $result->reasons
                    ]);
                    $this->log($bill->id, 'rejected_by_fraud');
                    $bill->markDuplicate();
                    return;
                }

                if ($decision === 'review') {
                    Log::info('Bill sent for review', [
                        'bill_id' => $bill->id,
                        'score' => $result->score,
                    ]);
                    $this->log($bill->id, 'sent_to_review');
                    $bill->markReview();
                    return;
                }

                // -------------------------
                // ✅ SAVE SUCCESS
                // -------------------------

                try {
                    $bill->markDone([
                        'invoice_number' => $normalized['invoice'],
                        'amount' => $normalized['amount'],
                        'vendor_name' => $normalized['vendor'],
                        'bill_date' => $normalized['date'],
                        'raw_text' => json_encode($mapped['raw']),
                        'confidence'=> $normalized['confidence'],
                        'provider' => $mapped['provider'],
                        'semantic_hash' => $semanticHash,
                    ]);

                    $items = $mapper->extractLineItems(
                        $mapped['raw'],
                        $mapped['provider']
                    );

                    $prepared = $lineItemService->prepare(
                        $items,
                        $bill->id,
                        $mapped['provider']
                    );

                    foreach (array_chunk($prepared, 100) as $chunk) {
                        BillItem::insert($chunk);
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), 'semantic_hash')) {
                        $bill->markDuplicate();
                        return;
                    }

                    throw $e;
                }

                // -------------------------
                // ✅ IDEMPOTENT POINTS
                // -------------------------

                LoyaltyPoint::firstOrCreate(
                    ['bill_id' => $bill->id],
                    [
                        'user_id' => $bill->user_id,
                        'points' => $pointsEarned,
                    ]
                );
                $completed = true;
            });
            if ($completed) {
                Log::info('JOB SUCCESS', ['bill_id' => $bill->id]);
                $this->log($bill->id, 'completed', [
                    'duration_ms' => round((microtime(true) - $start) * 1000)
                ]);
            } else {
                Log::info('JOB FINISHED WITHOUT COMPLETION', ['bill_id' => $bill->id]);
            }

        } catch (\Throwable $e) {
            $freshBill = Bill::find($this->billId);

            if ($freshBill && $freshBill->status === Bill::STATUS_PENDING) {
                $freshBill->markFailed();
            }

            $this->log($this->billId, 'failed', $e->getMessage());
            Log::error('Bill Processing Failed', [
                'bill_id' => $this->billId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function failed(\Throwable $e)
    {
        Log::error('JOB FAILED HARD', [
            'bill_id' => $this->billId,
            'error' => $e->getMessage()
        ]);
        $this->log($this->billId, 'failed', $e->getMessage());
    }

    private function log($billId, $status, $message = null): void
    {
        \App\Modules\Loyalty\Models\BillLog::create([
            'bill_id' => $billId,
            'status' => $status,
            'message' => is_array($message) ? json_encode($message) : $message,
        ]);
    }

    private function generateSemanticHash(array $n): string
    {
        return sha1(json_encode([
            'amount' => round((float)$n['amount'], 2),

            'date' => $n['date']
                ? Carbon::parse($n['date'])->format('Y-m-d')
                : null,

            'vendor' => Str::lower(
                preg_replace('/\s+/', '', $n['vendor'] ?? '')
            ),

            'invoice' => Str::lower(
                preg_replace('/[^a-zA-Z0-9]/', '', $n['invoice'] ?? '')
            ),
        ]));
    }
}