<?php

namespace App\Http\Livewire;

use App\Models\PhoneSchedule;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Utils\PhoneScheduleTimeFormatter;
use App\Services\TimeParser;
use Carbon\Carbon;

class PhoneScheduleTable extends LivewireTableComponent
{
    protected $model = PhoneSchedule::class;

    public string $tableName = 'phone_schedules';

    public $direction = 'given_by_me';

    public function mount($direction = 'given_by_me')
    {
        $this->direction = $direction;
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setDefaultSort('phone_schedules.created_at', 'desc');
        $this->setPerPageAccepted([5, 10, 25, 50]);
        $this->setPerPage(5);
        $this->setTdAttributes(function (Column $column, $row, $columnIndex, $rowIndex) {
            return [
                'style' => 'padding-top: 14px; padding-bottom: 14px;',
            ];
        });
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.common.name'))
                ->searchable()
                ->label(fn ($row, Column $column) => view('phone_schedule.component.name', [
                    'row' => $row,
                    'direction' => $this->direction,
                    'party' => $this->direction === 'given_by_me'
                        ? $row->otherPartyByPhone
                        : $row->user,
                ])),
            Column::make(__('messages.common.phone_number'), 'phone_number_normalized')
                ->label(fn ($row, Column $column) => view('phone_schedule.component.phone', [
                    'row' => $row,
                ])),
            Column::make(__('messages.common.email'))
                ->label(fn ($row, Column $column) => view('phone_schedule.component.email', [
                    'row' => $row,
                    'direction' => $this->direction,
                    'party' => $this->direction === 'given_by_me'
                        ? $row->otherPartyByPhone
                        : $row->user,
                ])),
            Column::make(__('messages.common.link'))
                ->label(fn ($row, Column $column) => view('phone_schedule.component.link', [
                    'row' => $row,
                    'direction' => $this->direction,
                    'party' => $this->direction === 'given_by_me'
                        ? $row->otherPartyByPhone
                        : $row->user,
                ])),
            Column::make(__('messages.event.phone_schedule_name'))
                ->label(fn ($row, Column $column) => view('phone_schedule.component.schedule_name', [
                    'row' => $row,
                ])),
            Column::make(__('messages.schedule_event.scheduled_time'))
                ->label(fn ($row, Column $column) => view('phone_schedule.component.times', [
                    'row' => $row,
                ])),
            Column::make(__('messages.schedule_event.status'))
                ->label(fn ($row, Column $column) => view('phone_schedule.component.status', [
                    'row' => $row,
                ])),
        ];
    }


    public function builder(): Builder
    {
        $userId = getLogInUserId();
        $myPhone = getLoginUser()->phone_number ?? null;
        $today = PhoneScheduleTimeFormatter::todayWeekdayCode();

        $query = PhoneSchedule::query()
            ->select('phone_schedules.*')
            ->with(['user', 'otherPartyByPhone', 'schedule.userSchedules', 'userSchedules']);

        if ($this->direction === 'given_to_me') {
            $query->where('phone_number_normalized', $myPhone);
        } else {
            $query->where('phone_schedules.user_id', $userId);
        }

        
        $query->where(function ($q) use ($today) {
            $q->whereHas('userSchedules', fn ($sq) => $sq->where('day_of_week', $today))
              ->orWhereHas('schedule.userSchedules', fn ($sq) => $sq->where('day_of_week', $today));
        });

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

        $flat = collect();

        foreach ($all as $row) {
            foreach (PhoneScheduleTimeFormatter::ranges($row) as $range) {
                $clone = clone $row;
                $clone->_singleRange = $range;
                $flat->push($clone);
            }
        }

        $search = trim($this->getSearch() ?? '');
        if ($search !== '') {
            $flat = $flat->filter(fn ($row) => $this->matchesSearch($this->searchableCellsForRow($row), $search))->values();
        }

        $sorted = $flat->sort(function ($a, $b) {
            $aActive = $a->_singleRange['active'];
            $bActive = $b->_singleRange['active'];

            if ($aActive !== $bActive) {
                return $aActive ? -1 : 1;
            }

            return $aActive
                ? $this->toMinutes($a->_singleRange['to']) <=> $this->toMinutes($b->_singleRange['to'])
                : $this->toMinutes($a->_singleRange['from']) <=> $this->toMinutes($b->_singleRange['from']);
        })->values();

        $perPage = $this->getPerPage();
        $currentPage = $this->paginators[$pageName];

        $lastPage = max(1, (int) ceil($sorted->count() / $perPage));
        if ($currentPage > $lastPage) {
            $currentPage = $lastPage;
            $this->paginators[$pageName] = $currentPage;
        }

        $currentPageItems = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $sorted->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => $pageName]
        );
    }

    private function toMinutes(?string $time): int
    {
        if (! $time) return -1;
        $parser = app(TimeParser::class);
        $now = Carbon::now('Asia/Kolkata');
        $parsed = $parser->parseFlexibleTime($time, $now);
        return $parsed ? ($parsed->hour * 60 + $parsed->minute) : -1;
    }

    private function searchableCellsForRow($row): array
    {
        $party = $this->direction === 'given_by_me' ? $row->otherPartyByPhone : $row->user;

        return [
            $party->full_name ?? '',
            $party->email ?? '',
            $row->phone_number_normalized ?? '',
            optional($row->schedule)->schedule_name ?? '',
            $row->_singleRange['from'] ?? '',
            $row->_singleRange['to'] ?? '',
            ($row->_singleRange['active'] ?? false) ? 'active' : 'upcoming',
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