<?php

namespace App\Http\Livewire;

use App\Models\EventSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;

class EventScheduleTable extends LivewireTableComponent
{
    protected $model = EventSchedule::class;

    public string $tableName = 'event_schedules';

    public $status = '';
    public $direction = 'given_by_me';

    public function mount($eventStatus, $direction = 'given_by_me')
    {
        $this->status = $eventStatus;
        $this->direction = $direction;
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setDefaultSort('event_schedules.created_at', 'desc');
        $this->setPerPageAccepted([5, 10, 25, 50]);
        $this->setPerPage(5);
        $this->setTdAttributes(function (Column $column, $row, $columnIndex, $rowIndex) {
            if ($columnIndex == '10') {
                return [
                    'style' => 'text-align:center,padding-left:0!important',
                    'width' => '8%',
                ];
            }

            return [];
        });
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.common.name'))
                ->sortable()->searchable()
                ->label(fn ($row, Column $column) => view('schedule_event.component.name', [
                    'row' => $row,
                    'component' => $this,
                ])),
            Column::make('Phone call', 'phone_call')->hideIf(true),
            Column::make(__('messages.common.email'))
                ->searchable()
                ->label(fn ($row, Column $column) => view('schedule_event.component.email', [
                    'row' => $row,
                    'component' => $this,
                ])),
           Column::make(__('messages.common.link'))
                ->label(fn ($row, Column $column) => view('schedule_event.component.link', [
                    'row' => $row,
                    'component' => $this,
                ]))->hideIf(true),
            Column::make('User id', 'user_id')->hideIf(true),
            Column::make(__('messages.event.event_name'), 'event.name')
                ->searchable()->view('schedule_event.component.event_name'),
            Column::make(__('messages.event.event_type'), 'event.event_type')
                ->searchable()->view('schedule_event.component.event_type'),
            Column::make(__('messages.event.event_name'), 'event_id')->hideIf(true),
            Column::make(__('messages.schedule_event.scheduled_date'), 'schedule_date')
                ->sortable()->view('schedule_event.component.scheduled_date'),
            Column::make(__('messages.schedule_event.scheduled_time'), 'slot_time')
                ->sortable()->view('schedule_event.component.scheduled_time'),
            Column::make(__('messages.event.meeting_type'))
                ->label(fn ($row, Column $column) => view('schedule_event.component.location', [
                    'row' => $row,
                    'component' => $this,
                ])),
            Column::make('User schedule id', 'user_schedule_id')->hideIf(true),
            Column::make(__('messages.schedule_event.status'), 'status')
                ->view('schedule_event.component.status'),
            Column::make(__('messages.common.action'), 'id')
                ->label(fn ($row, Column $column) => view('schedule_event.component.action', [
                    'row' => $row,
                    'component' => $this,
                ])),
        ];
    }

    public function builder(): Builder
    {
        $userId = getLogInUserId();
        $myPhone = getLoginUser()->phone_number ?? null;

        // $query = EventSchedule::with(['event','userGoogleEventSchedule'])->where('event_schedules.user_id', getLogInUserId());

        $query = EventSchedule::with(['event.location', 'event.user', 'userGoogleEventSchedule', 'userZoomEventSchedule', 'user', 'otherPartyByPhone']);

        if ($this->direction === 'given_to_me') {
            $query->where('phone_call', $myPhone);
        } else {
            $query->where('event_schedules.user_id', $userId);
        }

        if ($this->status == 1) {
            $query->where('schedule_date', '=', Carbon::now('Asia/Kolkata')->format('Y-m-d'));
        } else {
            $query->where('schedule_date', '>=', Carbon::now('Asia/Kolkata')->format('Y-m-d'));
        }

        if (! $this->hasSorts()) {
            $query->orderBy('event_schedules.schedule_date', 'asc');
        }

        return $query;
    }

    public function getRows()
    {
        \Log::info('SEARCH_DEBUG', ['search' => $this->search, 'query_param' => request()->query('event_schedules')]);
        $pageName = $this->getComputedPageName();

        if (!isset($this->paginators[$pageName])) {
            $this->paginators[$pageName] = (int) request()->query($pageName, 1);
        }

        $all = $this->builder()->get();
        $parser = app(\App\Services\TimeParser::class);
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();

        $filtered = $all->filter(function ($row) use ($parser, $now, $today) {
            if ($row->schedule_date !== $today) {
                return true; // else branch already guarantees future-only
            }

            $parts = explode('-', $row->slot_time ?? '');
            $endRaw = trim($parts[1] ?? '');
            $end = $endRaw ? $parser->parseFlexibleTime($endRaw, $now) : null;

            return $end === null || $end->greaterThanOrEqualTo($now);
        })->values();

        $search = trim($this->getSearch() ?? '');
        \Log::info('SEARCH_DEBUG_2', ['getSearch_value' => $this->getSearch(), 'raw_search_prop' => $this->search ?? 'undefined']);
        if ($search !== '') {
            $filtered = $filtered->filter(fn ($row) => $this->matchesSearch($this->searchableCellsForRow($row), $search))->values();
        }

        $perPage = $this->getPerPage();
        $currentPage = $this->paginators[$pageName];

        $lastPage = max(1, (int) ceil($filtered->count() / $perPage));
        if ($currentPage > $lastPage) {
            $currentPage = $lastPage;
            $this->paginators[$pageName] = $currentPage;
        }

        $currentPageItems = $filtered->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $filtered->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => $pageName]
        );
    }

    public function resetPageTable()
    {
        $this->customResetPage('event_schedulesPage');
    }

    private function searchableCellsForRow($row): array
    {
        $party = $this->direction === 'given_to_me' ? $row->user : null;

        $locationMeta = optional($row->event)->location_meta;
        $locationAddress = '';
        if ($locationMeta) {
            $decoded = json_decode($locationMeta, true);
            if (is_array($decoded) && isset($decoded[1])) {
                $locationAddress = $decoded[1];
            }
        }

        if ($this->direction === 'given_to_me') {
            return [
                $party->full_name ?? '',
                $party->email ?? '',
                optional($row->event)->name ?? '',
                optional($row->event)->event_type ?? '',
                $row->schedule_date ?? '',
                $row->slot_time ?? '',
                EventSchedule::STATUS[$row->status] ?? '',
                $locationAddress,
            ];
        }

        return [
            $row->name ?? '',
            $row->email ?? '',
            optional($row->event)->name ?? '',
            optional($row->event)->event_type ?? '',
            $row->schedule_date ?? '',
            $row->slot_time ?? '',
            EventSchedule::STATUS[$row->status] ?? '',
            $locationAddress,
        ];
    }

    private function matchesSearch(array $cells, string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }

        $tokens = preg_split('/\s+/', mb_strtolower($query));

        foreach ($tokens as $token) {
            $found = false;
            foreach ($cells as $cell) {
                if ($cell !== null && str_contains(mb_strtolower((string) $cell), $token)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }
}
