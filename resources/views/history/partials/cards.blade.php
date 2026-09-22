@php
  $cardStr = function($cards) {
    if (!$cards) return '—';
    // Ensure we have a flat array of card strings
    $arr = is_array($cards) ? $cards : [$cards];
    
    // Flatten in case of nested arrays from Eloquent casts
    $flatArr = [];
    array_walk_recursive($arr, function($a) use (&$flatArr) { $flatArr[] = $a; });

    return collect($flatArr)->map(function($c) {
      $val = is_array($c) ? json_encode($c) : (string)$c;
      return '<span class="badge bg-dark border border-secondary text-white me-1" style="font-family:monospace">'
      . htmlspecialchars($val) . '</span>';
    })->implode('');
  };
@endphp

@if($game === 'baccarat')
  <span class="text-secondary" style="font-size:.75rem">P:</span> {!! $cardStr($row['player_cards']) !!}
  <span class="text-secondary ms-1" style="font-size:.75rem">B:</span> {!! $cardStr($row['banker_cards']) !!}
@elseif($game === 'andarbahar')
  <span class="text-secondary" style="font-size:.75rem">J:</span> {!! $cardStr([$row['joker_card']]) !!}
@elseif($game === 'dragontiger')
  <span class="text-secondary" style="font-size:.75rem">D:</span> {!! $cardStr([$row['dragon_card']]) !!}
  <span class="text-secondary ms-1" style="font-size:.75rem">T:</span> {!! $cardStr([$row['tiger_card']]) !!}
@else
  <span class="text-secondary" style="font-size:.75rem">P:</span> {!! $cardStr($row['player_cards']) !!}
@endif