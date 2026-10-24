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
                  'roulette'       => ['red','black','green'],
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
              @if($normalizedGame === 'baccarat') <th>Shoe</th> @endif
              <th>Time</th>
              <th>Tab ID</th>
              <th>{{ $normalizedGame === 'roulette' ? 'Result' : 'Cards' }}</th>
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

              @if($normalizedGame === 'baccarat')
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
                  $w = strtolower($row['winner'] ?? ($row['winning_colour'] ?? ''));
                  $winnerColor = match($w) {
                    'player','andar' => 'primary',
                    'banker','bahar' => 'danger',
                    'tie'            => 'success',
                    'red'            => 'danger',
                    'black'          => 'dark border border-secondary',
                    'green'          => 'success',
                    default          => 'warning',
                  };
                  $winnerLabel = !empty($row['winner']) 
                    ? strtoupper($row['winner']) 
                    : (isset($row['winning_number']) ? '#' . $row['winning_number'] . ' ' . strtoupper($row['winning_colour'] ?? '') : '—');
                @endphp
                <span class="badge bg-{{ $winnerColor }}">{{ $winnerLabel }}</span>
              </td>

              <td class="text-secondary small">
                @if(is_array($row['bet_position']))
                  @foreach($row['bet_position'] as $key => $val)
                    @if(is_array($val))
                      <span class="{{ !empty($val['is_win']) ? 'text-success fw-bold' : '' }}">
                        {{ $val['label'] ?? $val['position'] }}: ${{ number_format($val['amount'] ?? 0, 0) }}
                      </span>{{ !$loop->last ? ", " : "" }}
                    @else
                      {{ is_numeric($key) ? $val : "$key:$val" }}{{ !$loop->last ? ", " : "" }}
                    @endif
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
                @if(!empty($row['side_win']))
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
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-black border-warning">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title text-warning"><i class="bi bi-card-list me-2"></i>Round Detail</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="historyModalBody">
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

  if (game === 'roulette') {
    return renderRouletteDetail(d, fmt);
  }

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
  } else if (game === 'roulette') {
    const col = (d.winning_colour || '').toLowerCase();
    const colClass = col === 'red' ? 'danger' : (col === 'black' ? 'dark border border-secondary' : 'success');
    cards = `<div class="p-3 mb-2 rounded border border-warning" style="background:#2b0509">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-secondary small d-block">Roulette Winning Outcome:</span>
          <span class="badge bg-${colClass} fs-5 px-3 py-2 fw-bold mt-1">
            #${d.winning_number ?? '—'} ${(d.winning_colour ?? '').toUpperCase()}
          </span>
        </div>
        <div class="text-end">
          <span class="text-secondary small d-block">Tab Identifier:</span>
          <span class="text-warning fw-bold fs-6">${d.tab_id || 'N/A'}</span>
          <div class="small text-secondary mt-1">${d.roulette_type || 'European'} Roulette</div>
        </div>
      </div>
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
  if (Array.isArray(d.bet_position)) {
    betPosHtml = d.bet_position.map(b => {
      if (typeof b === 'object' && b !== null) {
        const isWin = b.is_win;
        const winBadge = isWin 
          ? `<span class="badge bg-success ms-1">+${fmt(b.win_amount)}</span>` 
          : `<span class="badge bg-secondary ms-1">Lost</span>`;
        return `<div class="d-flex justify-content-between border-bottom border-secondary py-1">
          <span><span class="text-warning fw-bold">${b.label || b.position}</span> <small class="text-secondary">(${b.rule_type || 'Bet'})</small></span>
          <span>$${fmt(b.amount || 0)} ${winBadge}</span>
        </div>`;
      }
      return b;
    }).join('');
  } else if (typeof d.bet_position === 'object' && d.bet_position !== null) {
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

// ── 156-Position Roulette Visual Modal Board Renderer ─────────────────────────
function renderRouletteDetail(d, fmt) {
  const winNum = (d.winning_number !== undefined && d.winning_number !== null) ? parseInt(d.winning_number) : null;
  const winCol = (d.winning_colour || '').toLowerCase();
  const colBadge = winCol === 'red' ? 'danger' : (winCol === 'black' ? 'dark border border-secondary' : 'success');

  const bets = Array.isArray(d.bet_position) ? d.bet_position : [];

  const RED_NUMS = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
  const numToPosIdx = n => {
    if (n === 0) return 144;
    if (n % 3 === 0) { const c = n / 3; return (2 * c - 1) * 6; }
    if (n % 3 === 2) { const c = (n + 1) / 3; return (2 * c - 1) * 6 + 2; }
    const c = (n + 2) / 3; return (2 * c - 1) * 6 + 4;
  };

  const aliasMap = {
    '0': 144, 'STRAIGHT_0': 144,
    '1-12': 145, 'DOZEN_1': 145, 'DOZ_1': 145,
    '13-24': 146, 'DOZEN_2': 146, 'DOZ_2': 146,
    '25-36': 147, 'DOZEN_3': 147, 'DOZ_3': 147,
    '1-18': 148, 'LOW': 148, 'LOW_1_18': 148,
    'EVEN': 149,
    'RED': 150,
    'BLACK': 151,
    'ODD': 152,
    '19-36': 153, 'HIGH': 153, 'HIGH_19_36': 153,
    'COL_3': 154, 'COLUMN_3': 154, '2-1_ROW_1': 154,
    'COL_2': 155, 'COLUMN_2': 155, '2-1_ROW_2': 155,
    'COL_1': 156, 'COLUMN_1': 156, '2-1_ROW_3': 156,
  };

  const resolveBetIdx = b => {
    if (b.position_index !== undefined && b.position_index !== null) {
      return parseInt(b.position_index);
    }
    const raw = String(b.position || '').toUpperCase().trim();
    if (aliasMap[raw] !== undefined) return aliasMap[raw];
    if (!isNaN(raw)) {
      const v = parseInt(raw);
      if (v >= 0 && v <= 36) {
        if (!b.rule_type || b.rule_type === 'Straight Up' || b.rule_type === 'STRAIGHT') {
          return numToPosIdx(v);
        }
      }
      if (v >= 0 && v <= 156) return v;
    }
    return null;
  };

  // Group bets by position index
  const betsByPos = {};
  bets.forEach(b => {
    const idx = resolveBetIdx(b);
    if (idx !== null) {
      if (!betsByPos[idx]) betsByPos[idx] = [];
      betsByPos[idx].push(b);
    }
  });

  const row1 = [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36];
  const row2 = [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35];
  const row3 = [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34];

  // Helper to render placed chips
  const renderChips = idx => {
    const arr = betsByPos[idx] || [];
    if (!arr.length) return '';
    return arr.map(b => {
      const isWin = b.is_win;
      const chipCls = isWin ? 'chip-win-glow' : 'chip-lost-dark';
      return `<span class="r-chip ${chipCls}" title="${b.label || ('Pos #' + idx)}: $${fmt(b.amount)} (${isWin ? 'Won $' + fmt(b.win_amount) : 'Lost'})">
        $${parseInt(b.amount)}
      </span>`;
    }).join('');
  };

  // Build grid coordinate dots (0 to 143)
  let gridDotsHtml = '';
  for (let i = 0; i <= 143; i++) {
    const k = Math.floor(i / 6);
    const r = i % 6;
    let leftPct = 0;
    if (k === 0) {
      leftPct = 0;
    } else if (k % 2 === 1) {
      const col = (k + 1) / 2;
      leftPct = ((col - 0.5) / 12) * 100;
    } else {
      const col = k / 2;
      leftPct = (col / 12) * 100;
    }
    const topMap = [16.667, 33.333, 50.0, 66.667, 83.333, 100.0];
    const topPct = topMap[r];

    const chips = renderChips(i);

    gridDotsHtml += `<div class="r-pos-dot-wrap" style="left:${leftPct}%; top:${topPct}%">
      <span class="r-purple-dot" data-pos="${i}">${i}</span>
      ${chips}
    </div>`;
  }

  // Row 1 cells HTML
  const row1Html = row1.map(n => {
    const isRed = RED_NUMS.includes(n);
    const isWin = (winNum === n);
    return `<div class="r-num-cell flex-fill ${isRed ? 'r-cell-red' : 'r-cell-black'} ${isWin ? 'r-win-pocket' : ''}">${n}</div>`;
  }).join('');

  // Row 2 cells HTML
  const row2Html = row2.map(n => {
    const isRed = RED_NUMS.includes(n);
    const isWin = (winNum === n);
    return `<div class="r-num-cell flex-fill ${isRed ? 'r-cell-red' : 'r-cell-black'} ${isWin ? 'r-win-pocket' : ''}">${n}</div>`;
  }).join('');

  // Row 3 cells HTML
  const row3Html = row3.map(n => {
    const isRed = RED_NUMS.includes(n);
    const isWin = (winNum === n);
    return `<div class="r-num-cell flex-fill ${isRed ? 'r-cell-red' : 'r-cell-black'} ${isWin ? 'r-win-pocket' : ''}">${n}</div>`;
  }).join('');

  // Outside win checks
  const d1Win = (winNum !== null && winNum >= 1 && winNum <= 12);
  const d2Win = (winNum !== null && winNum >= 13 && winNum <= 24);
  const d3Win = (winNum !== null && winNum >= 25 && winNum <= 36);

  const lowWin = (winNum !== null && winNum >= 1 && winNum <= 18);
  const evenWin = (winNum !== null && winNum > 0 && winNum % 2 === 0);
  const redWin = (winNum !== null && winCol === 'red');
  const blackWin = (winNum !== null && winCol === 'black');
  const oddWin = (winNum !== null && winNum > 0 && winNum % 2 !== 0);
  const highWin = (winNum !== null && winNum >= 19 && winNum <= 36);

  const col3Win = (winNum !== null && winNum > 0 && row1.includes(winNum));
  const col2Win = (winNum !== null && winNum > 0 && row2.includes(winNum));
  const col1Win = (winNum !== null && winNum > 0 && row3.includes(winNum));

  // Build Placed Bets Table rows
  let betsTableRows = '';
  if (bets.length > 0) {
    bets.forEach((b, idxNum) => {
      const idx = resolveBetIdx(b);
      const isWin = b.is_win;
      const posBadge = idx !== null 
        ? `<span class="badge px-2 py-1 me-1" style="background:#9333ea; font-size:.8rem">Dot #${idx}</span>`
        : `<span class="badge bg-secondary px-2 py-1 me-1">Custom</span>`;
      
      const statusBadge = isWin
        ? `<span class="badge bg-success fw-bold px-2 py-1"><i class="bi bi-check-circle me-1"></i>WON</span>`
        : `<span class="badge bg-secondary px-2 py-1"><i class="bi bi-x-circle me-1"></i>LOST</span>`;
      
      const winDisplay = isWin 
        ? `<span class="text-success fw-bold">+$${fmt(b.win_amount)}</span>`
        : `<span class="text-secondary">$0.00</span>`;

      const numStr = Array.isArray(b.numbers) && b.numbers.length 
        ? (b.numbers.length > 8 ? `${b.numbers.slice(0, 5).join(',')}... (${b.numbers.length} nums)` : b.numbers.join(', '))
        : '—';

      betsTableRows += `<tr>
        <td class="text-secondary">${idxNum + 1}</td>
        <td>${posBadge} <span class="text-warning fw-bold">${b.label || b.position}</span></td>
        <td><span class="badge bg-dark border border-secondary text-white">${b.rule_type || 'Bet'}</span></td>
        <td class="text-secondary small" style="max-width:200px">${numStr}</td>
        <td class="text-end text-warning fw-bold">$${fmt(b.amount || 0)}</td>
        <td class="text-center text-white">${b.multiplier ? (b.multiplier + ':1') : '—'}</td>
        <td class="text-center">${statusBadge}</td>
        <td class="text-end">${winDisplay}</td>
      </tr>`;
    });
  } else {
    betsTableRows = `<tr><td colspan="8" class="text-center text-muted py-3">No individual bets recorded for this record.</td></tr>`;
  }

  return `
    <div class="roulette-modal-view">
      
      {{-- Outcome Ticker Bar --}}
      <div class="d-flex align-items-center justify-content-between p-2 mb-3 rounded" style="background:rgba(0,0,0,0.5); border:2px solid #eab308">
        <div class="d-flex align-items-center gap-3">
          <span class="text-warning fw-bold fs-5">&gt;</span>
          <div>
            <span class="text-secondary small d-block">Winning Pocket Outcome:</span>
            <span class="badge bg-${colBadge} fs-5 px-3 py-1 fw-bold">
              #${winNum ?? '—'} ${winCol.toUpperCase()}
            </span>
          </div>
          <div class="ms-3 border-start border-secondary ps-3">
            <span class="text-secondary small d-block">Tab Identifier:</span>
            <span class="badge bg-secondary fs-6">${d.tab_id || 'N/A'}</span>
          </div>
          <div class="ms-2 border-start border-secondary ps-3">
            <span class="text-secondary small d-block">Round / Game No:</span>
            <span class="text-warning fw-bold">${d.game_no || 'N/A'}</span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div class="text-end">
            <span class="text-secondary small d-block">Total Wager:</span>
            <span class="text-warning fw-bold fs-6">$${fmt(d.bet_amount)}</span>
          </div>
          <div class="text-end border-start border-secondary ps-3">
            <span class="text-secondary small d-block">Total Payout:</span>
            <span class="text-success fw-bold fs-6">+$${fmt(d.win_amount)}</span>
          </div>
          <div class="text-end border-start border-secondary ps-3">
            <span class="text-secondary small d-block">Balance After:</span>
            <span class="text-white fw-bold fs-6">$${fmt(d.current_credit)}</span>
          </div>
        </div>
      </div>

      {{-- Controls Toolbar --}}
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-outline-info btn-sm active" id="btnTogglePosDots" onclick="toggleRoulettePosDots()">
            <i class="bi bi-bullseye me-1"></i><span id="lblTogglePosDots">Hide Bet Dots (0-156)</span>
          </button>
          <span class="badge bg-dark border border-secondary text-secondary small">Reference: 156 Physical Bet Coordinates</span>
        </div>
        <div class="d-flex align-items-center gap-3 small text-secondary">
          <span><span class="legend-chip legend-chip-win me-1"></span> Winning Bet</span>
          <span><span class="legend-chip legend-chip-lost me-1"></span> Lost Bet</span>
          <span><span class="r-purple-dot-mini me-1"></span> Position Dot (0-156)</span>
        </div>
      </div>

      {{-- The Felt Roulette Board matching WhatsApp Image 2026-10-21 at 6.49.23 PM.jpeg --}}
      <div class="r-modal-felt mb-3 show-purple-dots" id="rouletteFeltBoard">
        
        {{-- Call Bets Row --}}
        <div class="d-flex justify-content-between gap-1 mb-1">
          <div class="r-call-btn flex-fill">0-SPIEL</div>
          <div class="r-call-btn flex-fill">SERIES 0/2/3</div>
          <div class="r-call-btn flex-fill">ORPHELINS</div>
          <div class="r-call-btn flex-fill">SERIES 5/8</div>
          <div class="r-call-btn flex-fill">CROWN</div>
          <div class="r-call-btn flex-fill">NEIGHBOURS</div>
        </div>

        {{-- Main Grid Area with Zero, Numbers, and Columns --}}
        <div class="d-flex mb-1 position-relative">
          
          {{-- Zero Cell (Dot 144) --}}
          <div class="r-zero-box d-flex align-items-center justify-content-center ${winNum === 0 ? 'r-win-pocket' : ''}">
            0
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="144">144</span>
              ${renderChips(144)}
            </div>
          </div>

          {{-- 12 Number Columns with Overlaid Coordinate Points (0 to 143) --}}
          <div class="r-grid-container flex-grow-1 position-relative">
            
            {{-- Numbers Grid (3 rows x 12 cols) --}}
            <div class="d-flex flex-column">
              <div class="d-flex">${row1Html}</div>
              <div class="d-flex">${row2Html}</div>
              <div class="d-flex">${row3Html}</div>
            </div>

            {{-- 144 Coordinate Points Layer (Dots 0 to 143) --}}
            <div class="r-dots-layer position-absolute top-0 start-0 w-100 h-100 pointer-events-none">
              ${gridDotsHtml}
            </div>

          </div>

          {{-- Columns 2-1 (Dots 154, 155, 156) --}}
          <div class="d-flex flex-column" style="width:60px">
            {{-- Row 1 Col (Dot 154) --}}
            <div class="r-col-cell flex-fill ${col3Win ? 'r-win-pocket' : ''}">
              2-1
              <div class="r-pos-dot-wrap position-absolute top-50 start-50">
                <span class="r-purple-dot" data-pos="154">154</span>
                ${renderChips(154)}
              </div>
            </div>
            {{-- Row 2 Col (Dot 155) --}}
            <div class="r-col-cell flex-fill ${col2Win ? 'r-win-pocket' : ''}">
              2-1
              <div class="r-pos-dot-wrap position-absolute top-50 start-50">
                <span class="r-purple-dot" data-pos="155">155</span>
                ${renderChips(155)}
              </div>
            </div>
            {{-- Row 3 Col (Dot 156) --}}
            <div class="r-col-cell flex-fill ${col1Win ? 'r-win-pocket' : ''}">
              2-1
              <div class="r-pos-dot-wrap position-absolute top-50 start-50">
                <span class="r-purple-dot" data-pos="156">156</span>
                ${renderChips(156)}
              </div>
            </div>
          </div>

        </div>

        {{-- Dozens Row (Dots 145, 146, 147) --}}
        <div class="d-flex gap-1 mb-1 ms-zero-offset">
          <div class="r-outside-cell flex-fill ${d1Win ? 'r-win-pocket' : ''}">
            1-12
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="145">145</span>
              ${renderChips(145)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${d2Win ? 'r-win-pocket' : ''}">
            13-24
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="146">146</span>
              ${renderChips(146)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${d3Win ? 'r-win-pocket' : ''}">
            25-36
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="147">147</span>
              ${renderChips(147)}
            </div>
          </div>
          <div class="r-icon-placeholder d-flex align-items-center justify-content-center px-2">
            <svg width="26" height="18" viewBox="0 0 40 24" fill="none">
              <rect x="2" y="2" width="36" height="20" rx="10" stroke="#c026d3" stroke-width="2" stroke-dasharray="2 2" fill="none" />
              <rect x="18" y="2" width="4" height="20" fill="#22c55e" />
            </svg>
          </div>
        </div>

        {{-- Outside Even-Money Row (Dots 148, 149, 150, 151, 152, 153) --}}
        <div class="d-flex gap-1 ms-zero-offset">
          <div class="r-outside-cell flex-fill ${lowWin ? 'r-win-pocket' : ''}">
            1-18
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="148">148</span>
              ${renderChips(148)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${evenWin ? 'r-win-pocket' : ''}">
            EVEN
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="149">149</span>
              ${renderChips(149)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${redWin ? 'r-win-pocket' : ''}">
            <span style="color:#ef4444; font-size:1.6rem; line-height:1">♦</span>
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="150">150</span>
              ${renderChips(150)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${blackWin ? 'r-win-pocket' : ''}">
            <span style="color:#09090b; -webkit-text-stroke: 1.5px rgba(255,255,255,0.75); font-size:1.6rem; line-height:1">♦</span>
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="151">151</span>
              ${renderChips(151)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${oddWin ? 'r-win-pocket' : ''}">
            ODD
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="152">152</span>
              ${renderChips(152)}
            </div>
          </div>
          <div class="r-outside-cell flex-fill ${highWin ? 'r-win-pocket' : ''}">
            19-36
            <div class="r-pos-dot-wrap position-absolute top-50 start-50">
              <span class="r-purple-dot" data-pos="153">153</span>
              ${renderChips(153)}
            </div>
          </div>
          <div class="r-icon-placeholder d-flex align-items-center justify-content-center px-2">
            <span class="mini-chip-token"></span>
          </div>
        </div>

      </div>

      {{-- Placed Bets Details Table --}}
      <div class="card bg-black border-secondary">
        <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center py-2">
          <span class="text-warning fw-bold small"><i class="bi bi-list-check me-2"></i>Placed Bets Breakdown for Tab: ${d.tab_id || 'N/A'}</span>
          <span class="text-secondary small">Total Bets Placed: ${bets.length}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-dark table-hover table-sm mb-0 align-middle" style="font-size:.85rem">
            <thead class="border-secondary text-secondary text-uppercase" style="font-size:.75rem">
              <tr>
                <th>#</th>
                <th>Bet Position</th>
                <th>Rule Type</th>
                <th>Covered Numbers</th>
                <th class="text-end">Wager</th>
                <th class="text-center">Multiplier</th>
                <th class="text-center">Outcome</th>
                <th class="text-end">Payout Win</th>
              </tr>
            </thead>
            <tbody>
              ${betsTableRows}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  `;
}

function toggleRoulettePosDots() {
  const board = document.getElementById('rouletteFeltBoard');
  const lbl = document.getElementById('lblTogglePosDots');
  const btn = document.getElementById('btnTogglePosDots');
  if (board) {
    board.classList.toggle('show-purple-dots');
    const isShowing = board.classList.contains('show-purple-dots');
    if (lbl) lbl.textContent = isShowing ? 'Hide Bet Dots (0-156)' : 'Show Bet Dots (0-156)';
    if (btn) btn.classList.toggle('active', isShowing);
  }
}
</script>

<style>
/* 156-Position Roulette Modal Board Styling matching WhatsApp Image 2026-10-21 at 6.49.23 PM.jpeg */
.r-modal-felt {
  background: radial-gradient(ellipse at center, #560810 0%, #340409 65%, #160103 100%);
  border: 2px solid #7f1d1d;
  box-shadow: inset 0 0 35px rgba(0, 0, 0, 0.8), 0 8px 24px rgba(0, 0, 0, 0.7);
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  user-select: none;
  padding: 12px;
  border-radius: 8px;
}
.r-call-btn {
  background: #18181b;
  border: 1px solid rgba(255, 255, 255, 0.65);
  border-radius: 4px;
  font-weight: 700;
  font-size: 0.8rem;
  color: #fff;
  padding: 5px 3px;
  text-align: center;
}
.r-zero-box {
  width: 62px;
  background: radial-gradient(circle at center, #22c55e 0%, #15803d 60%, #14532d 100%);
  border: 1px solid rgba(255,255,255,0.65);
  font-size: 2.1rem;
  font-family: Georgia, serif;
  font-weight: 700;
  color: #fff;
  position: relative;
}
.r-num-cell {
  height: 44px;
  line-height: 42px;
  border: 1px solid rgba(255,255,255,0.65);
  font-size: 1.35rem;
  font-family: Georgia, serif;
  font-weight: 700;
  color: #fff;
  text-align: center;
}
.r-cell-red { background: #c8102e; }
.r-cell-black { background: #111111; }
.r-col-cell {
  border: 1px solid rgba(255,255,255,0.65);
  background: #1c0306;
  font-size: 1.1rem;
  font-weight: 700;
  color: #fff;
  position: relative;
}
.ms-zero-offset {
  margin-left: 62px;
  margin-right: 60px;
}
.r-outside-cell {
  background: #3c0409;
  border: 1px solid rgba(255,255,255,0.65);
  border-radius: 4px;
  font-weight: 700;
  font-size: 1.05rem;
  color: #fff;
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.r-icon-placeholder {
  width: 48px;
  border: 1px solid rgba(255, 255, 255, 0.35);
  background: rgba(0, 0, 0, 0.4);
  border-radius: 4px;
}
.mini-chip-token {
  display: inline-block;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: radial-gradient(circle, #f43f5e 30%, #881337 100%);
  border: 2px dashed #ffffff;
}

/* Purple Position Dots matching the reference image */
.r-pos-dot-wrap {
  position: absolute;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
  z-index: 10;
}
.r-purple-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #9333ea;
  border: 1.5px solid #ffffff;
  color: #ffffff;
  font-size: 9px;
  font-weight: 800;
  display: none; /* Controlled by .show-purple-dots */
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 4px rgba(0,0,0,0.85);
  user-select: none;
}
.show-purple-dots .r-purple-dot {
  display: inline-flex !important;
}
.r-purple-dot:hover {
  transform: scale(1.35);
  z-index: 25;
}
.r-purple-dot-mini {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #9333ea;
  border: 1px solid #ffffff;
  vertical-align: middle;
}

/* Casino Chip Overlay */
.r-chip {
  min-width: 26px;
  height: 20px;
  border-radius: 10px;
  border: 1.5px dashed #ffffff;
  font-size: 10px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,0.9);
  padding: 0 4px;
  z-index: 15;
  transition: transform 0.2s;
  cursor: pointer;
}
.r-chip:hover {
  transform: scale(1.4);
  z-index: 30;
}
.chip-win-glow {
  background: radial-gradient(circle, #10b981 0%, #047857 100%);
  border-color: #fef08a;
  color: #ffffff;
  box-shadow: 0 0 10px #10b981;
}
.chip-lost-dark {
  background: radial-gradient(circle, #4b5563 0%, #1f2937 100%);
  border-color: rgba(255,255,255,0.7);
  color: #f3f4f6;
  opacity: 0.9;
}

/* Winning Pocket Cell Glow */
.r-win-pocket {
  position: relative;
  z-index: 5;
  border: 2.5px solid #fbbf24 !important;
  box-shadow: inset 0 0 15px #f59e0b, 0 0 18px 4px #fbbf24 !important;
  animation: pulse-gold-pocket 1.4s infinite alternate ease-in-out;
}
@keyframes pulse-gold-pocket {
  from { box-shadow: inset 0 0 10px #f59e0b, 0 0 12px 2px #fbbf24; }
  to { box-shadow: inset 0 0 18px #f59e0b, 0 0 22px 5px #fef08a; }
}

.legend-chip {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  vertical-align: middle;
}
.legend-chip-win {
  background: #10b981;
  border: 1px dashed #fef08a;
}
.legend-chip-lost {
  background: #4b5563;
  border: 1px dashed #ffffff;
}
</style>
@endpush
</x-app-layout>