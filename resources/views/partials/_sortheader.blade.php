@if ($params['order'] == $field)
    <a href="{{ request()->fullUrlWithQuery(['order' => $field, 'order_direction' => ($params['order_direction'] == 'asc' ? 'desc' : 'asc')]) }}">{{ $title }}</a>
    <i class="fe fe-chevron-{{ $params['order_direction'] == 'asc' ? 'down' : 'up' }}"></i>
@else
    <a href="{{ request()->fullUrlWithQuery(['order' => $field, 'order_direction' => (isset($direction) ? $direction : 'asc')]) }}">{{ $title }}</a>
@endif
