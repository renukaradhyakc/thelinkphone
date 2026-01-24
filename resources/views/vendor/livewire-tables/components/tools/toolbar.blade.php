@aware(['component'])

@php
    $theme = $component->getTheme();
@endphp
<div class="d-md-flex justify-content-between mb-3 livewire-search-box align-items-center">
    <div class="d-md-flex">
        @if ($component->reorderIsEnabled())
            <div class="me-0 me-md-2 mb-3 mb-md-0">
                <button wire:click="{{ $component->currentlyReorderingIsEnabled() ? 'disableReordering' : 'enableReordering' }}"
                        type="button"
                        class="btn btn-default d-block w-100 d-md-inline">
                    @if ($component->currentlyReorderingIsEnabled())
                        @lang('Done Reordering')
                    @else
                        @lang('Reorder')
                    @endif
                </button>
            </div>
        @endif
            @if ($component->searchIsEnabled() && $component->searchVisibilityIsEnabled())
            <div class="card-title">
                <div class="position-relative d-flex custom-width-300px">
<span class="position-absolute d-flex align-items-center top-0 bottom-0 left-0 text-gray-600 ms-3">
                       <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="search"
                           wire:model{{ $component->getSearchOptions() }}="{{ $component->getTableName() }}.search"
                           data-datatable-filter="search" name="search"
                           class="form-control w-250px ps-8" placeholder="{{ __('messages.common.search') }}"/>
                </div>
            </div>
        @endif
        @if ($component->filtersAreEnabled() && $component->filtersVisibilityIsEnabled() && $component->hasFilters())
            <div class="{{ $component->searchIsEnabled() ? 'ms-0 ms-md-2' : '' }} mb-3 mb-md-0">
                <div @if ($component->isFilterLayoutPopover())
                     x-data="{ open: false }"
                     x-on:keydown.escape.stop="open = false"
                     x-on:mousedown.away="open = false"
                     @endif
                     class="btn-group d-block d-md-inline">
                    <div>

                        <a href="javascript:void(0)" class="btn btn-flex btn-light-primary fw-bolder"
                           @if ($component->isFilterLayoutPopover())
                           x-on:click="open = !open"
                               aria-haspopup="true"
                               x-bind:aria-expanded="open"
                               aria-expanded="true"
                               @endif

                               @if ($component->isFilterLayoutSlideDown())
                               x-on:click="filtersOpen = !filtersOpen"
                                    @endif
                        >
                                <span class="svg-icon svg-icon-5 svg-icon-gray-500 me-1">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         width="24px" height="24px"
                                         viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24"></rect>
                                            <path
                                                    d="M5,4 L19,4 C19.2761424,4 19.5,4.22385763 19.5,4.5 C19.5,4.60818511 19.4649111,4.71345191 19.4,4.8 L14,12 L14,20.190983 C14,20.4671254 13.7761424,20.690983 13.5,20.690983 C13.4223775,20.690983 13.3458209,20.6729105 13.2763932,20.6381966 L10,19 L10,12 L4.6,4.8 C4.43431458,4.5790861 4.4790861,4.26568542 4.7,4.1 C4.78654809,4.03508894 4.89181489,4 5,4 Z"
                                                    fill="#000000"></path>
                                        </g>
                                    </svg>
                                </span>
                            {{ __('messages.common.filter') }}
                        </a>
                    </div>

                    @if ($component->isFilterLayoutPopover())
                        <ul wire:key='{{ $component->getTableName() }}-filters-popover-menu'
                            x-cloak
                            class="dropdown-menu w-100 p-0"
                            x-bind:class="{'show' : open}"
                            role="menu">
                            <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px show">
                                <div class="px-7 py-5">
                                    <div class="fs-5 text-dark fw-bolder">{{ __('messages.common.filter_options') }}</div>
                                </div>
                                <div class="separator border-gray-200"></div>
                                <div class="px-7 py-5">
                                    @foreach($component->getFilters() as $filter)
                                        <div class="mb-10"
                                             wire:key="{{ $component->getTableName() }}-filter-{{ $filter->getKey() }}">
                                            <label class="form-label fs-6 fw-bold"
                                                   for="{{ $component->getTableName() }}-filter-{{ $filter->getKey() }}">
                                                {{ $filter->getName() }}
                                            </label>
                                                {{ $filter->render($component) }}
                                            </div>
                                        @endforeach
                                        @if ($component->hasAppliedFiltersWithValues())
                                            <div class="d-flex justify-content-end">
                                                <button class="btn btn-sm btn-light btn-active-light-primary me-2"
                                                        wire:click.prevent="setFilterDefaults"
                                                        x-on:click="open = false">{{ __('messages.common.reset') }}</button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </ul>
                        @endif
                    </div>
                </div>
                @endif
    </div>

    <div class="d-flex justify-content-end">
        @if ($component->showFilterOnHeader == true)
                @include($component->FilterComponent[0],  ['filterHeads' => [$component->FilterComponent[1] , isset($component->FilterComponent[2]) ? $component->FilterComponent[2] : '']] )
            @endif
            @if ($component->showButtonOnHeader)
                @include($component->buttonComponent)
            @endif
        </div>
    </div>
