<x-app-layout>
<div class="container-fluid py-3">

  {{-- Header --}}
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="text-warning mb-0"><i class="bi bi-clock-history me-2"></i>Game History</h4>
      @if($selectedTable)
        <small class="text-secondary">
          {{ $selectedTable->table_name }} &mdash; {{ $selectedTable->gameType->name }}
        </small>
      @endif
    </div>
  </div>

  {{-- Filters --}}
  <div class="card bg-black border-secondary mb-3">
    <div class="card-body py-2">
      <form method="GET" action="{{ route('history.index') }}" id="filterForm">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label text-secondary small mb-1">Game Type</label>
            <select name="game" class="form-select form-select-sm bg-black text-white border-secondary" id="gameTypeSelect">
              <option value="">-- Select Game --</option>
              @foreach($gameTypes as $gt)
                <option value="{{ $gt->code }}" {{ $selectedGame == $gt->code ? 'selected' : '' }}>
                  {{ $gt->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label text-secondary small mb-1">Table</label>
            <select name="table_id" class="form-select form-select-sm bg-black text-white border-secondary" id="tableSelect">
              <option value="">-- Select Table --</option>
              @foreach($tables as $t)
                <option value="{{ $t->id }}"
                  data-game="{{ $t->gameType->code }}"
                  {{ request('table_id') == $t->id ? 'selected' : '' }}>
                  {{ $t->table_name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label text-secondary small mb-1">Game No</label>
            <input type="text" name="game_no" value="{{ request('game_no') }}"
              class="form-control form-control-sm bg-black text-white border-secondary" placeholder="G#...">
          </div>
          <div class="col-md-1">
            <label class="form-label text-secondary small mb-1">Tab ID</label>
            @if(empty($tabIds))
              <select name="tab_id" class="form-select form-select-sm bg-black text-secondary border-secondary" disabled>
                <option>— Select Table —</option>
              </select>
            @else
              <select name="tab_id" class="form-select form-select-sm bg-black text-white border-secondary">
                <option value="">All Tabs</option>
                @foreach($tabIds as $tid)
                  <option value="{{ $tid }}" {{ request('tab_id') == $tid ? 'selected' : '' }}>
                    {{ $tid }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>
          <div class="col-md-2">
            <label class="form-label text-secondary small mb-1">Date</label>
            <input type="date" name="date" value="{{ request('date') }}"
              class="form-control form-control-sm bg-black text-white border-secondary">
          </div>
          <div class="col-md-2">
            <label class="form-label text-secondary small mb-1">Winner</label>
            <select name="winner" class="form-select form-select-sm bg-black text-white border-secondary">
              <option value="">All</option>
              @php
                $winnerOptions = match($normalizedGame) {
                  'baccarat'       => ['player','banker','tie'],
                  'andarbahar'     => ['andar','bahar'],
                  'dragontiger'    => ['dragon','tiger','tie'],
                  'threecardpoker','miniflush','casinowar','blackjack' => ['player','dealer','tie'],
                  default          => []
                };
              @endphp
              @foreach($winnerOptions as $w)
                <option value="{{ $w }}" {{ request('winner') == $w ? 'selected' : '' }}>
                  {{ ucfirst($w) }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-warning btn-sm flex-fill">
              <i class="bi bi-search"></i>
            </button>
            <a href="{{ route('history.index') }}" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-x"></i>
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Results --}}
  @if(!$normalizedGame || !request('table_id'))
    <div class="text-center text-secondary py-5">
      <i class="bi bi-funnel" style="font-size:2rem"></i>
      <p class="mt-2">Select a game type and table to view history.</p>
    </div>
  @elseif(empty($records['data']))
    <div class="text-center text-secondary py-5">
      <i class="bi bi-inbox" style="font-size:2rem"></i>
      <p class="mt-2">No records found for this table.</p>
    </div>
  @else

    {{-- Game Navigation --}}
    @if(isset($records['game_no']))
    <div class="card bg-black border-secondary mb-3">
      <div class="card-body d-flex align-items-center justify-content-between py-2">
        <div class="d-flex align-items-center gap-3">
          <span class="text-secondary small">Game Session:</span>
          <span class="text-warning fw-bold fs-5">{{ $records['game_no'] ?? 'N/A' }}</span>
        </div>
        
        <div class="d-flex align-items-center gap-2">
          <div class="btn-group">
            <a href="{{ request()->fullUrlWithQuery(['game_no' => $records['prev_game_no']]) }}" 
               class="btn btn-outline-warning btn-sm {{ !$records['prev_game_no'] ? 'disabled' : '' }}"
               title="Older Game">
              <i class="bi bi-chevron-left"></i> Older
            </a>
            
            <button class="btn btn-dark btn-sm disabled text-white border-secondary px-3" style="opacity: 1">
              {{ $records['current_page'] }} / {{ $records['last_page'] }}
            </button>

            <a href="{{ request()->fullUrlWithQuery(['game_no' => $records['next_game_no']]) }}" 
               class="btn btn-outline-warning btn-sm {{ !$records['next_game_no'] ? 'disabled' : '' }}"
               title="Newer Game">
              Newer <i class="bi bi-chevron-right"></i>
            </a>
          </div>

          {{-- Optional dropdown to jump to a specific game --}}
          <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
              Jump to
            </button>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow" style="max-height: 300px; overflow-y: auto;">
              @foreach($records['all_game_nos'] as $gn)
                <li>
                  <a class="dropdown-item {{ $gn == $records['game_no'] ? 'active' : '' }}" 
                     href="{{ request()->fullUrlWithQuery(['game_no' => $gn]) }}">
                    {{ $gn }}
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      </div>
    </div>
    @endif

    {{-- Summary Strip --}}
    @php
      $data      = collect($records['data']);
      $totalBet  = $data->sum('bet_amount');
      $totalWin  = $data->sum('win_amount');
      $totalNet  = $totalWin - $totalBet;
    @endphp
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="card bg-black border-secondary text-center py-2">
          <div class="text-secondary small">Players in Game</div>
          <div class="text-white fw-bold">{{ count($records['data']) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card bg-black border-secondary text-center py-2">
          <div class="text-secondary small">Total Bet (Game)</div>
          <div class="text-warning fw-bold">{{ number_format($totalBet, 2) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card bg-black border-secondary text-center py-2">
          <div class="text-secondary small">Total Win (Game)</div>
          <div class="text-success fw-bold">{{ number_format($totalWin, 2) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card bg-black border-secondary text-center py-2">
          <div class="text-secondary small">Net (Game)</div>
          <div class="fw-bold {{ $totalNet >= 0 ? 'text-success' : 'text-danger' }}">
            {{ number_format($totalNet, 2) }}
          </div>
        </div>
      </div>
    </div>

    {{-- Table --}}
    <div class="card bg-black border-secondary">
      <div class="table-responsive">
        <table class="table table-dark table-hover table-sm mb-0 align-middle" style="font-size:.85rem">
          <thead class="border-secondary">
            <tr class="text-secondary text-uppercase" style="font-size:.75rem">
              <th>#</th>
              <th>Game No</th>
              @if(in_array($normalizedGame, ['baccarat','dragontiger'])) <th>Shoe</th> @endif
              <th>Time</th>
              <th>Tab ID</th>
              <th>Cards</th>
              <th>Winner</th>
              <th>Bet Position</th>
              <th class="text-end">Bet</th>
              <th class="text-end">Win</th>
              <th class="text-end">Credit</th>
              <th>Side</th>
            </tr>
          </thead>
          <tbody>
            @foreach($records['data'] as $row)
            <tr class="history-row" data-id="{{ $row['id'] }}" style="cursor:pointer">
              <td class="text-secondary">{{ $row['id'] }}</td>
              <td class="text-warning fw-bold">{{ $row['game_no'] }}</td>

              @if(in_array($normalizedGame, ['baccarat','dragontiger']))
                <td class="text-secondary">{{ $row['shoe_no'] ?? '-' }}</td>
              @endif

              <td class="text-secondary small">
                {{ \Carbon\Carbon::parse($row['date_time'])->format('d/m H:i:s') }}
              </td>

              <td>
                @if($row['tab_id'])
                  <span class="badge bg-secondary">{{ $row['tab_id'] }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>

              {{-- Cards column — game-specific --}}
              <td>
                @include('history.partials.cards', ['row' => $row, 'game' => $normalizedGame])
              </td>

              {{-- Winner badge --}}
              <td>
                @php
                  $winnerColor = match(strtolower($row['winner'])) {
                    'player','andar','dragon' => 'primary',
                    'banker','bahar','tiger'  => 'danger',
                    'tie'                     => 'success',
                    'dealer'                  => 'danger',
                    'blackjack'               => 'warning',
                    default                   => 'secondary',
                  };
                @endphp
                <span class="badge bg-{{ $winnerColor }}">{{ strtoupper($row['winner']) }}</span>
              </td>

              <td class="text-secondary small">
                @if(is_array($row['bet_position']))
                  @foreach($row['bet_position'] as $key => $val)
                    {{ is_numeric($key) ? $val : "$key:$val" }}{{ !$loop->last ? ", " : "" }}
                  @endforeach
                @else
                  {{ $row['bet_position'] }}
                @endif
              </td>
              <td class="text-end text-warning">{{ number_format($row['bet_amount'], 2) }}</td>
              <td class="text-end {{ $row['win_amount'] > 0 ? 'text-success' : 'text-secondary' }}">
                {{ number_format($row['win_amount'], 2) }}
              </td>
              <td class="text-end text-white">{{ number_format($row['current_credit'], 2) }}</td>
              <td>
                @if($row['side_win'])
                  <span class="badge bg-warning text-dark" style="font-size:.7rem">
                    @if(is_array($row['side_win']))
                      {{ implode(', ', $row['side_win']) }}
                    @else
                      {{ $row['side_win'] }}
                    @endif
                  </span>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      @if($records['last_page'] > 1)
      <div class="card-footer bg-black border-secondary d-flex justify-content-between align-items-center py-2">
        <small class="text-secondary">
          Showing {{ $records['from'] }}–{{ $records['to'] }} of {{ $records['total'] }}
        </small>
        <div class="d-flex gap-1">
          @for($p = 1; $p <= $records['last_page']; $p++)
            <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}"
               class="btn btn-sm {{ $p == $records['current_page'] ? 'btn-warning' : 'btn-outline-secondary' }}">
               {{ $p }}
            </a>
          @endfor
        </div>
      </div>
      @endif
    </div>

  @endif
</div>

{{-- Row Detail Modal --}}
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-black border-warning">
      <div class="modal-header border-secondary">
        <h6 class="modal-title text-warning"><i class="bi bi-card-list me-2"></i>Round Detail</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="historyModalBody">
        <div class="text-center text-secondary py-3"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>


@push('scripts')
<script>
// Filter: auto-filter table dropdown by game type
document.getElementById('gameTypeSelect')?.addEventListener('change', function() {
  const game = this.value;
  const tableSelect = document.getElementById('tableSelect');
  [...tableSelect.options].forEach(opt => {
    opt.hidden = game ? (opt.dataset.game !== game && opt.value !== '') : false;
  });
  tableSelect.value = '';
});

// Row click → modal detail
document.querySelectorAll('.history-row').forEach(row => {
  row.addEventListener('click', function() {
    const id   = this.dataset.id;
    const game = '{{ $normalizedGame }}';
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    document.getElementById('historyModalBody').innerHTML =
      '<div class="text-center text-secondary py-3"><span class="spinner-border spinner-border-sm"></span></div>';
    modal.show();
    fetch(`/api/v1/history/${game}/${id}`)
      .then(r => {
        if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
        return r.json();
      })
      .then(data => {
        document.getElementById('historyModalBody').innerHTML = renderDetail(data, game);
      })
      .catch(err => {
        console.error('Fetch error:', err);
        document.getElementById('historyModalBody').innerHTML = 
          `<div class="alert alert-danger">Failed to load round details: ${err.message}</div>`;
      });
  });
});

function renderDetail(d, game) {
  const fmt = n => parseFloat(n).toLocaleString('en', {minimumFractionDigits:2});
  let cards = '';

  if (game === 'baccarat') {
    cards = `<div class="row g-2">
      <div class="col-6"><div class="text-secondary small mb-1">Player</div>${renderCards(d.player_cards)}</div>
      <div class="col-6"><div class="text-secondary small mb-1">Banker</div>${renderCards(d.banker_cards)}</div>
    </div>`;
  } else if (game === 'andarbahar') {
    cards = `<div class="mb-2"><span class="text-secondary small">Joker: </span>${renderCards([d.joker_card])}</div>
    <div class="row g-2">
      <div class="col-6"><div class="text-secondary small mb-1">Andar</div>${renderCards(d.andar_cards)}</div>
      <div class="col-6"><div class="text-secondary small mb-1">Bahar</div>${renderCards(d.bahar_cards)}</div>
    </div>`;
  } else if (game === 'dragontiger') {
    cards = `<div class="row g-2">
      <div class="col-6"><div class="text-secondary small mb-1">Dragon</div>${renderCards([d.dragon_card])}</div>
      <div class="col-6"><div class="text-secondary small mb-1">Tiger</div>${renderCards([d.tiger_card])}</div>
    </div>`;
  } else {
    cards = `<div class="row g-2">
      <div class="col-6"><div class="text-secondary small mb-1">Player</div>${renderCards(d.player_cards)}</div>
      <div class="col-6"><div class="text-secondary small mb-1">Dealer</div>${renderCards(d.dealer_cards)}</div>
    </div>`;
  }

  // Blackjack splits
  let splitHtml = '';
  if (game === 'blackjack' && d.split_hands?.length) {
    splitHtml = '<div class="mt-3"><div class="text-secondary small mb-1">Split Hands</div>';
    d.split_hands.forEach(s => {
      splitHtml += `<div class="border border-secondary rounded p-2 mb-2">
        <div class="d-flex justify-content-between mb-1">
          <span class="text-secondary small">Hand #${s.split_no}</span>
          <span class="badge bg-${s.winner==='player'?'primary':'danger'}">${s.winner.toUpperCase()}</span>
        </div>
        ${renderCards(s.cards)}
        <div class="d-flex gap-3 mt-1" style="font-size:.8rem">
          <span class="text-secondary">Bet: <span class="text-warning">${fmt(s.bet_amount)}</span></span>
          <span class="text-secondary">Double: <span class="text-warning">${fmt(s.double_amount)}</span></span>
          <span class="text-secondary">Win: <span class="text-success">${fmt(s.win_amount)}</span></span>
        </div>
      </div>`;
    });
    splitHtml += '</div>';
  }

  let betPosHtml = '';
  if (typeof d.bet_position === 'object' && d.bet_position !== null) {
    betPosHtml = Object.entries(d.bet_position).map(([k, v]) => 
      isNaN(k) ? `${k}:${v}` : v
    ).join(', ');
  } else {
    betPosHtml = d.bet_position || '—';
  }

  let sideWinHtml = '';
  if (Array.isArray(d.side_win)) {
    sideWinHtml = d.side_win.join(', ');
  } else {
    sideWinHtml = d.side_win ?? '—';
  }

  return `<div>
    ${cards}
    ${splitHtml}
    <hr class="border-secondary my-3">
    <div class="row g-3" style="font-size:.9rem">
      <div class="col-6 col-md-3"><div class="text-secondary small">Winner</div>
        <span class="badge bg-primary">${d.winner.toUpperCase()}</span></div>
      <div class="col-6 col-md-3"><div class="text-secondary small">Bet Position</div>
        <div class="text-white">${betPosHtml}</div></div>
      <div class="col-6 col-md-3"><div class="text-secondary small">Bet Amount</div>
        <div class="text-warning fw-bold">${fmt(d.bet_amount)}</div></div>
      <div class="col-6 col-md-3"><div class="text-secondary small">Win Amount</div>
        <div class="text-success fw-bold">${fmt(d.win_amount)}</div></div>
      ${game==='blackjack' ? `
      <div class="col-6 col-md-3"><div class="text-secondary small">Double</div>
        <div class="text-white">${fmt(d.double_amount)}</div></div>
      <div class="col-6 col-md-3"><div class="text-secondary small">Insurance</div>
        <div class="text-white">${fmt(d.insurance_amount)}</div></div>` : ''}
      <div class="col-6 col-md-3"><div class="text-secondary small">Current Credit</div>
        <div class="text-white">${fmt(d.current_credit)}</div></div>
      <div class="col-6 col-md-3"><div class="text-secondary small">Side Win</div>
        <div class="text-warning">${sideWinHtml}</div></div>
      <div class="col-12"><div class="text-secondary small">Date / Time</div>
        <div class="text-secondary">${d.date_time}</div></div>
    </div>
  </div>`;
}

function renderCards(cards) {
  if (!cards) return '<span class="text-muted">—</span>';
  
  const flatten = (arr) => arr.reduce((acc, val) => 
    Array.isArray(val) ? acc.concat(flatten(val)) : acc.concat(val), []
  );
  
  const rawArr = Array.isArray(cards) ? cards : [cards];
  const flatArr = flatten(rawArr);
  
  const suitMap = {'h':'♥','d':'♦','c':'♣','s':'♠'};
  const suitColor = s => ['h','d'].includes(s) ? '#dc3545' : '#fff';
  const suitSymbol = s => suitMap[s] || s;
  
  return flatArr.map(c => {
    if (!c) return '';
    const str = String(c).trim();
    if (!str) return '';
    
    let rank, suit;
    const firstChar = str[0].toLowerCase();
    const lastChar  = str[str.length - 1].toLowerCase();
    
    if (suitMap[firstChar]) {
      // Format: SuitRank (e.g., H10, SA)
      suit = firstChar;
      rank = str.slice(1);
    } else if (suitMap[lastChar]) {
      // Format: RankSuit (e.g., 10H, AS)
      suit = lastChar;
      rank = str.slice(0, -1);
    } else {
      // Unknown format - fallback to raw
      return `<span class="badge bg-dark border border-secondary text-white me-1">${str}</span>`;
    }

    return `<span class="d-inline-flex align-items-center justify-content-center border border-secondary rounded me-1 mb-1"
      style="width:32px;height:42px;font-size:.8rem;font-weight:700;color:${suitColor(suit)};background:#111">
      ${rank}<span style="font-size:.7rem">${suitSymbol(suit)}</span></span>`;
  }).join('');
}
</script>
@endpush
</x-app-layout>