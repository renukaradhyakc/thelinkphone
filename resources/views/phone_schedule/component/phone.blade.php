@if(!empty($row->phone_number_normalized))
    <div class="d-inline-block align-top">
        <a href="tel:{{ $row->phone_number_normalized }}">
            {{ $row->phone_number_normalized }}
        </a>
    </div>
@else
    <div class="ps-2">—</div>
@endif