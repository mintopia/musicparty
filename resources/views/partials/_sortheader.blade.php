@if ($params['order'] == $field)
    <a href="{{ request()->fullUrlWithQuery(['order' => $field, 'order_direction' => ($params['order_direction'] == 'asc' ? 'desc' : 'asc')]) }}">{{ $title }}</a>
    <i class="icon ti ti-caret-{{ $params['order_direction'] == 'asc' ? 'down' : 'up' }}-filled"></i>
@else
    <a href="{{ request()->fullUrlWithQuery(['order' => $field, 'order_direction' => (isset($direction) ? $direction : 'asc')]) }}">{{ $title }}</a>
@endif
