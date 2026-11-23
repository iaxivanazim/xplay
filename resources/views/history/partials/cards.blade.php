@php
  $cardStr = function($cards) {
    if (!$cards) return '—';
    if (is_string($cards)) {
        $decoded = json_decode($cards, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $cards = $decoded;
        } else if (str_contains($cards, ',')) {
            $cards = explode(',', $cards);
        }
    }
    // Ensure we have a flat array of card strings
    $arr = is_array($cards) ? $cards : [$cards];
    
    // Flatten in case of nested arrays from Eloquent casts
    $flatArr = [];
    array_walk_recursive($arr, function($a) use (&$flatArr) {
        if ($a !== null && $a !== '') {
            $flatArr[] = $a;
        }
    });

    if (empty($flatArr)) return '—';

    return collect($flatArr)->map(function($c) {
      $val = is_array($c) ? json_encode($c) : (string)$c;
      return '<span class="badge bg-dark border border-secondary text-white me-1" style="font-family:monospace">'
      . htmlspecialchars($val) . '</span>';
    })->implode('');
  };
@endphp

@if($game === 'baccarat')
  <span class="text-secondary" style="font-size:.75rem">P:</span> {!! $cardStr($row['player_cards'] ?? null) !!}
  <span class="text-secondary ms-1" style="font-size:.75rem">B:</span> {!! $cardStr($row['banker_cards'] ?? null) !!}
@elseif($game === 'andarbahar')
  <span class="text-secondary" style="font-size:.75rem">J:</span> {!! $cardStr($row['joker_card'] ?? null) !!}
  @if(!empty($row['andar_cards']))
    <span class="text-secondary ms-1" style="font-size:.75rem">A:</span> {!! $cardStr($row['andar_cards']) !!}
  @endif
  @if(!empty($row['bahar_cards']))
    <span class="text-secondary ms-1" style="font-size:.75rem">B:</span> {!! $cardStr($row['bahar_cards']) !!}
  @endif
@elseif($game === 'roulette')
  @php
    $col = strtolower($row['winning_colour'] ?? '');
    $colorClass = match($col) {
      'red'   => 'bg-danger text-white',
      'black' => 'bg-dark text-white border border-secondary',
      'green' => 'bg-success text-white',
      default => 'bg-secondary text-white',
    };
  @endphp
  <span class="badge {{ $colorClass }} px-2 py-1 fw-bold">
    #{{ $row['winning_number'] ?? '—' }} {{ strtoupper($row['winning_colour'] ?? '') }}
  </span>
@else
  —
@endif