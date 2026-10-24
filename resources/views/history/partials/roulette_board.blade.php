{{-- 
  Roulette Visual Table Component
  Strictly designed based on the Virtuals Client Tablet Layout:
  'public/images/WhatsApp Image 2026-10-21 at 5.43.40 PM.jpeg'
--}}

@php
  $winningNumber = isset($winningNumber) ? (int)$winningNumber : ($row['winning_number'] ?? null);
  $winningColour = isset($winningColour) ? strtolower($winningColour) : strtolower($row['winning_colour'] ?? '');
  
  // Aggregate all bets for this round if passed or collect from records
  $allBets = $allBets ?? [];
  if (empty($allBets) && isset($records['data']) && is_iterable($records['data'])) {
    foreach ($records['data'] as $rec) {
      $tId = $rec['tab_id'] ?? 'Tab';
      if (!empty($rec['bet_position']) && is_array($rec['bet_position'])) {
        foreach ($rec['bet_position'] as $b) {
          $b['tab_id'] = $tId;
          $allBets[] = $b;
        }
      }
    }
  } elseif (empty($allBets) && isset($row['bet_position']) && is_array($row['bet_position'])) {
    foreach ($row['bet_position'] as $b) {
      $b['tab_id'] = $row['tab_id'] ?? 'Tab';
      $allBets[] = $b;
    }
  }

  // Pre-group bets by normalized board position
  $betsByPos = [];
  foreach ($allBets as $b) {
    $rawPos = strtoupper(trim((string)($b['position'] ?? '')));
    // Normalize numeric positions
    if (is_numeric($rawPos)) {
      $key = 'NUM_' . (int)$rawPos;
    } elseif (preg_match('/^STRAIGHT_?(\d+)$/', $rawPos, $m)) {
      $key = 'NUM_' . (int)$m[1];
    } elseif (in_array($rawPos, ['DOZEN_1', '1-12', 'DOZ_1'])) {
      $key = 'DOZEN_1';
    } elseif (in_array($rawPos, ['DOZEN_2', '13-24', 'DOZ_2'])) {
      $key = 'DOZEN_2';
    } elseif (in_array($rawPos, ['DOZEN_3', '25-36', 'DOZ_3'])) {
      $key = 'DOZEN_3';
    } elseif (in_array($rawPos, ['COL_1', 'COLUMN_1', '2-1_ROW_3', '2-1_COL_1'])) {
      $key = 'COL_1';
    } elseif (in_array($rawPos, ['COL_2', 'COLUMN_2', '2-1_ROW_2', '2-1_COL_2'])) {
      $key = 'COL_2';
    } elseif (in_array($rawPos, ['COL_3', 'COLUMN_3', '2-1_ROW_1', '2-1_COL_3'])) {
      $key = 'COL_3';
    } elseif (in_array($rawPos, ['LOW', '1-18', 'LOW_1_18'])) {
      $key = 'LOW_1_18';
    } elseif (in_array($rawPos, ['HIGH', '19-36', 'HIGH_19_36'])) {
      $key = 'HIGH_19_36';
    } else {
      $key = $rawPos;
    }

    if (!isset($betsByPos[$key])) {
      $betsByPos[$key] = [];
    }
    $betsByPos[$key][] = $b;
  }

  // Define 3 Rows of Numbers matching image layout:
  // Row 1: 3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36 (Top Row)
  $row1 = [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36];
  // Row 2: 2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35 (Middle Row)
  $row2 = [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35];
  // Row 3: 1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34 (Bottom Row)
  $row3 = [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34];

  $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
  $callBetsList = ['0-SPIEL', 'SERIES 0/2/3', 'ORPHELINS', 'SERIES 5/8', 'CROWN', 'NEIGHBOURS'];

  // Distinct tabs list for tab filtering
  $roundTabs = collect($allBets)->pluck('tab_id')->unique()->filter()->values()->all();
@endphp

<div class="roulette-felt-container p-3 mb-4 rounded-3 text-white">
  
  {{-- Header: Yellow Ticker Bar matching top of image --}}
  <div class="roulette-top-bar d-flex align-items-center justify-content-between px-3 py-2 mb-3">
    <div class="d-flex align-items-center gap-2">
      <span class="ticker-chevron fw-bold">&gt;</span>
      <span class="text-warning fw-bold text-uppercase" style="letter-spacing:1px; font-size:0.9rem;">
        Station Live Table Outcome:
      </span>
      @if($winningNumber !== null)
        <span class="badge {{ $winningColour === 'red' ? 'bg-danger' : ($winningColour === 'black' ? 'bg-dark border border-secondary' : 'bg-success') }} fs-6 px-2 py-1">
          POCKET #{{ $winningNumber }} {{ strtoupper($winningColour) }}
        </span>
      @else
        <span class="badge bg-secondary">Awaiting Draw</span>
      @endif
    </div>

    {{-- Filter by participating tab --}}
    @if(count($roundTabs) > 0)
    <div class="d-flex align-items-center gap-2">
      <span class="small text-secondary">Filter Tab:</span>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-warning btn-sm r-tab-filter active" data-tab="all">
          All Tabs ({{ count($roundTabs) }})
        </button>
        @foreach($roundTabs as $t)
          <button type="button" class="btn btn-outline-warning btn-sm r-tab-filter" data-tab="{{ $t }}">
            {{ $t }}
          </button>
        @endforeach
      </div>
    </div>
    @endif
  </div>

  {{-- The Felt Board Table --}}
  <div class="table-responsive">
    <div class="roulette-board-grid">

      {{-- 1. Call Bets Bar (Top Row) --}}
      <div class="call-bets-row d-flex justify-content-between gap-1 mb-1">
        @foreach($callBetsList as $callName)
          @php
            $callKey = strtoupper($callName);
            $hasBets = isset($betsByPos[$callKey]);
            $callPlaced = $hasBets ? $betsByPos[$callKey] : [];
            $isWinningCall = false;
            if ($winningNumber !== null) {
              $callNumbers = \App\Services\RouletteEngine::CALL_BETS[$callName] ?? [];
              $isWinningCall = in_array($winningNumber, $callNumbers, true);
            }
          @endphp
          <div class="call-bet-btn flex-fill text-center py-2 px-1 position-relative {{ $isWinningCall ? 'winning-call-hit' : '' }}"
               data-pos="{{ $callKey }}"
               title="{{ $callName }}">
            <span class="call-bet-label">{{ $callName }}</span>
            @if($hasBets)
              <div class="chips-stack">
                @foreach($callPlaced as $b)
                  <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                        data-tab="{{ $b['tab_id'] ?? '' }}"
                        title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                    ${{ (int)$b['amount'] }}
                  </span>
                @endforeach
              </div>
            @endif
          </div>
        @endforeach
      </div>

      {{-- 2. Numbers Grid with Zero on Left and 2-1 Columns on Right --}}
      <div class="main-numbers-table d-flex mb-1">
        
        {{-- Zero Cell (Spans 3 Rows) --}}
        @php
          $isZeroWin = ($winningNumber === 0);
          $hasZeroBets = isset($betsByPos['NUM_0']);
          $zeroBets = $hasZeroBets ? $betsByPos['NUM_0'] : [];
        @endphp
        <div class="zero-cell d-flex align-items-center justify-content-center position-relative {{ $isZeroWin ? 'winning-cell' : '' }}"
             data-pos="0">
          <span class="zero-number">0</span>
          @if($isZeroWin)
            <div class="winner-indicator"><i class="bi bi-star-fill text-warning"></i></div>
          @endif
          @if($hasZeroBets)
            <div class="chips-stack">
              @foreach($zeroBets as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- 3 Rows of 12 Columns --}}
        <div class="numbers-rows-container flex-grow-1">
          
          {{-- Row 1: Numbers 3, 6, 9... 36 and Col 3 (2-1) --}}
          <div class="num-row d-flex">
            @foreach($row1 as $num)
              @php
                $isRed = in_array($num, $redNumbers, true);
                $isHit = ($winningNumber === $num);
                $nKey = 'NUM_' . $num;
                $hasNBets = isset($betsByPos[$nKey]);
                $nBets = $hasNBets ? $betsByPos[$nKey] : [];
              @endphp
              <div class="num-cell flex-fill {{ $isRed ? 'cell-red' : 'cell-black' }} {{ $isHit ? 'winning-cell' : '' }} position-relative text-center"
                   data-pos="{{ $num }}">
                <span class="num-text">{{ $num }}</span>
                @if($isHit)
                  <div class="winner-indicator"><i class="bi bi-star-fill text-warning"></i></div>
                @endif
                @if($hasNBets)
                  <div class="chips-stack">
                    @foreach($nBets as $b)
                      <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                            data-tab="{{ $b['tab_id'] ?? '' }}"
                            title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                        ${{ (int)$b['amount'] }}
                      </span>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach
            
            {{-- Column 3 (2-1) --}}
            @php
              $col3Hit = ($winningNumber !== null && $winningNumber > 0 && in_array($winningNumber, $row1, true));
              $hasCol3Bets = isset($betsByPos['COL_3']);
              $col3Bets = $hasCol3Bets ? $betsByPos['COL_3'] : [];
            @endphp
            <div class="column-cell {{ $col3Hit ? 'winning-outside-hit' : '' }} position-relative text-center d-flex align-items-center justify-content-center"
                 data-pos="COL_3" title="Column 3 (2 to 1)">
              <span class="column-text">2-1</span>
              @if($hasCol3Bets)
                <div class="chips-stack">
                  @foreach($col3Bets as $b)
                    <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                          data-tab="{{ $b['tab_id'] ?? '' }}"
                          title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                      ${{ (int)$b['amount'] }}
                    </span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

          {{-- Row 2: Numbers 2, 5, 8... 35 and Col 2 (2-1) --}}
          <div class="num-row d-flex">
            @foreach($row2 as $num)
              @php
                $isRed = in_array($num, $redNumbers, true);
                $isHit = ($winningNumber === $num);
                $nKey = 'NUM_' . $num;
                $hasNBets = isset($betsByPos[$nKey]);
                $nBets = $hasNBets ? $betsByPos[$nKey] : [];
              @endphp
              <div class="num-cell flex-fill {{ $isRed ? 'cell-red' : 'cell-black' }} {{ $isHit ? 'winning-cell' : '' }} position-relative text-center"
                   data-pos="{{ $num }}">
                <span class="num-text">{{ $num }}</span>
                @if($isHit)
                  <div class="winner-indicator"><i class="bi bi-star-fill text-warning"></i></div>
                @endif
                @if($hasNBets)
                  <div class="chips-stack">
                    @foreach($nBets as $b)
                      <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                            data-tab="{{ $b['tab_id'] ?? '' }}"
                            title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                        ${{ (int)$b['amount'] }}
                      </span>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach

            {{-- Column 2 (2-1) --}}
            @php
              $col2Hit = ($winningNumber !== null && $winningNumber > 0 && in_array($winningNumber, $row2, true));
              $hasCol2Bets = isset($betsByPos['COL_2']);
              $col2Bets = $hasCol2Bets ? $betsByPos['COL_2'] : [];
            @endphp
            <div class="column-cell {{ $col2Hit ? 'winning-outside-hit' : '' }} position-relative text-center d-flex align-items-center justify-content-center"
                 data-pos="COL_2" title="Column 2 (2 to 1)">
              <span class="column-text">2-1</span>
              @if($hasCol2Bets)
                <div class="chips-stack">
                  @foreach($col2Bets as $b)
                    <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                          data-tab="{{ $b['tab_id'] ?? '' }}"
                          title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                      ${{ (int)$b['amount'] }}
                    </span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

          {{-- Row 3: Numbers 1, 4, 7... 34 and Col 1 (2-1) --}}
          <div class="num-row d-flex">
            @foreach($row3 as $num)
              @php
                $isRed = in_array($num, $redNumbers, true);
                $isHit = ($winningNumber === $num);
                $nKey = 'NUM_' . $num;
                $hasNBets = isset($betsByPos[$nKey]);
                $nBets = $hasNBets ? $betsByPos[$nKey] : [];
              @endphp
              <div class="num-cell flex-fill {{ $isRed ? 'cell-red' : 'cell-black' }} {{ $isHit ? 'winning-cell' : '' }} position-relative text-center"
                   data-pos="{{ $num }}">
                <span class="num-text">{{ $num }}</span>
                @if($isHit)
                  <div class="winner-indicator"><i class="bi bi-star-fill text-warning"></i></div>
                @endif
                @if($hasNBets)
                  <div class="chips-stack">
                    @foreach($nBets as $b)
                      <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                            data-tab="{{ $b['tab_id'] ?? '' }}"
                            title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                        ${{ (int)$b['amount'] }}
                      </span>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach

            {{-- Column 1 (2-1) --}}
            @php
              $col1Hit = ($winningNumber !== null && $winningNumber > 0 && in_array($winningNumber, $row3, true));
              $hasCol1Bets = isset($betsByPos['COL_1']);
              $col1Bets = $hasCol1Bets ? $betsByPos['COL_1'] : [];
            @endphp
            <div class="column-cell {{ $col1Hit ? 'winning-outside-hit' : '' }} position-relative text-center d-flex align-items-center justify-content-center"
                 data-pos="COL_1" title="Column 1 (2 to 1)">
              <span class="column-text">2-1</span>
              @if($hasCol1Bets)
                <div class="chips-stack">
                  @foreach($col1Bets as $b)
                    <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                          data-tab="{{ $b['tab_id'] ?? '' }}"
                          title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                      ${{ (int)$b['amount'] }}
                    </span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

        </div>
      </div>

      {{-- 3. Dozens Row (1-12, 13-24, 25-36) --}}
      @php
        $d1Hit = ($winningNumber !== null && $winningNumber >= 1 && $winningNumber <= 12);
        $d2Hit = ($winningNumber !== null && $winningNumber >= 13 && $winningNumber <= 24);
        $d3Hit = ($winningNumber !== null && $winningNumber >= 25 && $winningNumber <= 36);
        $hasD1 = isset($betsByPos['DOZEN_1']);
        $hasD2 = isset($betsByPos['DOZEN_2']);
        $hasD3 = isset($betsByPos['DOZEN_3']);
      @endphp
      <div class="dozens-row d-flex gap-1 mb-1 ms-zero-offset">
        <div class="outside-box flex-fill text-center py-2 position-relative {{ $d1Hit ? 'winning-outside-hit' : '' }}" data-pos="1-12">
          <span class="outside-label">1-12</span>
          @if($hasD1)
            <div class="chips-stack">
              @foreach($betsByPos['DOZEN_1'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        <div class="outside-box flex-fill text-center py-2 position-relative {{ $d2Hit ? 'winning-outside-hit' : '' }}" data-pos="13-24">
          <span class="outside-label">13-24</span>
          @if($hasD2)
            <div class="chips-stack">
              @foreach($betsByPos['DOZEN_2'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        <div class="outside-box flex-fill text-center py-2 position-relative {{ $d3Hit ? 'winning-outside-hit' : '' }}" data-pos="25-36">
          <span class="outside-label">25-36</span>
          @if($hasD3)
            <div class="chips-stack">
              @foreach($betsByPos['DOZEN_3'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }} ({{ !empty($b['is_win']) ? 'Won $' . number_format($b['win_amount'], 2) : 'Lost' }})">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Racetrack wheel miniature icon --}}
        <div class="racetrack-icon-btn d-flex align-items-center justify-content-center px-2">
          <svg width="28" height="20" viewBox="0 0 40 24" fill="none">
            <rect x="2" y="2" width="36" height="20" rx="10" stroke="#c026d3" stroke-width="2" stroke-dasharray="2 2" fill="none" />
            <rect x="18" y="2" width="4" height="20" fill="#22c55e" />
          </svg>
        </div>
      </div>

      {{-- 4. Even Money Outside Bets (1-18, EVEN, RED, BLACK, ODD, 19-36) --}}
      @php
        $lowHit   = ($winningNumber !== null && $winningNumber >= 1 && $winningNumber <= 18);
        $highHit  = ($winningNumber !== null && $winningNumber >= 19 && $winningNumber <= 36);
        $evenHit  = ($winningNumber !== null && $winningNumber > 0 && $winningNumber % 2 === 0);
        $oddHit   = ($winningNumber !== null && $winningNumber > 0 && $winningNumber % 2 !== 0);
        $redHit   = ($winningNumber !== null && $winningColour === 'red');
        $blackHit = ($winningNumber !== null && $winningColour === 'black');

        $hasLow   = isset($betsByPos['LOW_1_18']) || isset($betsByPos['1-18']);
        $hasHigh  = isset($betsByPos['HIGH_19_36']) || isset($betsByPos['19-36']);
        $hasEven  = isset($betsByPos['EVEN']);
        $hasOdd   = isset($betsByPos['ODD']);
        $hasRed   = isset($betsByPos['RED']);
        $hasBlack = isset($betsByPos['BLACK']);
      @endphp
      <div class="even-money-row d-flex gap-1 ms-zero-offset">
        
        {{-- 1-18 (Low) --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $lowHit ? 'winning-outside-hit' : '' }}" data-pos="1-18">
          <span class="outside-label">1-18</span>
          @if($hasLow)
            <div class="chips-stack">
              @foreach(($betsByPos['LOW_1_18'] ?? $betsByPos['1-18'] ?? []) as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- EVEN --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $evenHit ? 'winning-outside-hit' : '' }}" data-pos="EVEN">
          <span class="outside-label">EVEN</span>
          @if($hasEven)
            <div class="chips-stack">
              @foreach($betsByPos['EVEN'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- RED DIAMOND --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $redHit ? 'winning-outside-hit' : '' }}" data-pos="RED">
          <span class="diamond-icon red-diamond">♦</span>
          @if($hasRed)
            <div class="chips-stack">
              @foreach($betsByPos['RED'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- BLACK DIAMOND --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $blackHit ? 'winning-outside-hit' : '' }}" data-pos="BLACK">
          <span class="diamond-icon black-diamond">♦</span>
          @if($hasBlack)
            <div class="chips-stack">
              @foreach($betsByPos['BLACK'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- ODD --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $oddHit ? 'winning-outside-hit' : '' }}" data-pos="ODD">
          <span class="outside-label">ODD</span>
          @if($hasOdd)
            <div class="chips-stack">
              @foreach($betsByPos['ODD'] as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- 19-36 (High) --}}
        <div class="outside-box outside-pill flex-fill text-center py-2 position-relative {{ $highHit ? 'winning-outside-hit' : '' }}" data-pos="19-36">
          <span class="outside-label">19-36</span>
          @if($hasHigh)
            <div class="chips-stack">
              @foreach(($betsByPos['HIGH_19_36'] ?? $betsByPos['19-36'] ?? []) as $b)
                <span class="casino-chip {{ !empty($b['is_win']) ? 'chip-win' : 'chip-lost' }}"
                      data-tab="{{ $b['tab_id'] ?? '' }}"
                      title="{{ $b['tab_id'] }}: ${{ number_format($b['amount'], 2) }}">
                  ${{ (int)$b['amount'] }}
                </span>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Chip Selector Miniature Icon --}}
        <div class="chip-icon-btn d-flex align-items-center justify-content-center px-2">
          <div class="mini-chip-stack">
            <span class="mini-chip-token"></span>
          </div>
        </div>

      </div>

    </div>
  </div>

  {{-- Board Legend / Tab Bets Summary --}}
  <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top border-secondary small text-secondary">
    <div class="d-flex align-items-center gap-3">
      <span><span class="badge bg-warning text-dark me-1">★ Gold Border</span> Winning Pocket</span>
      <span><span class="legend-chip legend-chip-win me-1"></span> Winning Bet</span>
      <span><span class="legend-chip legend-chip-lost me-1"></span> Lost Bet</span>
    </div>
    <div>
      <span class="text-white fw-bold">Total Placed Bets:</span> {{ count($allBets) }} bets 
      &nbsp;|&nbsp; 
      <span class="text-warning fw-bold">Active Playing Tabs:</span> {{ count($roundTabs) }}
    </div>
  </div>

</div>

<style>
/* Felt Container matches deep casino crimson from the tablet interface */
.roulette-felt-container {
  background: radial-gradient(ellipse at center, #560810 0%, #340409 65%, #160103 100%);
  border: 2px solid #7f1d1d;
  box-shadow: inset 0 0 40px rgba(0, 0, 0, 0.8), 0 8px 24px rgba(0, 0, 0, 0.7);
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  user-select: none;
}

/* Yellow Ticker Bar matching top of image */
.roulette-top-bar {
  border: 2px solid #eab308;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.45);
}
.ticker-chevron {
  color: #eab308;
  font-size: 1.2rem;
}

/* Call Bets Row */
.call-bet-btn {
  background: #18181b;
  border: 1px solid rgba(255, 255, 255, 0.65);
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.2s ease;
  min-height: 38px;
}
.call-bet-btn:hover {
  background: #27272a;
}
.call-bet-label {
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #ffffff;
}
.winning-call-hit {
  border: 2px solid #fbbf24 !important;
  background: rgba(234, 179, 8, 0.25) !important;
  box-shadow: 0 0 10px rgba(250, 204, 21, 0.6);
}

/* Main Numbers Grid */
.main-numbers-table {
  border-radius: 6px;
  overflow: hidden;
}

/* Zero Cell */
.zero-cell {
  width: 68px;
  background: radial-gradient(circle at center, #22c55e 0%, #15803d 60%, #14532d 100%);
  border: 1px solid rgba(255, 255, 255, 0.65);
  cursor: pointer;
}
.zero-number {
  font-size: 2.2rem;
  font-weight: 700;
  font-family: Georgia, serif;
  color: #ffffff;
}

/* Number Cells */
.num-cell {
  height: 48px;
  line-height: 46px;
  border: 1px solid rgba(255, 255, 255, 0.65);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.cell-red {
  background: #c8102e;
}
.cell-black {
  background: #111111;
}
.num-text {
  font-size: 1.45rem;
  font-weight: 700;
  font-family: Georgia, serif;
  color: #ffffff;
}

/* 2-1 Column Cells */
.column-cell {
  width: 65px;
  border: 1px solid rgba(255, 255, 255, 0.65);
  background: #1c0306;
  cursor: pointer;
}
.column-text {
  font-size: 1.15rem;
  font-weight: 700;
  color: #ffffff;
}

/* Dozens & Outside Boxes */
.ms-zero-offset {
  margin-left: 68px;
  margin-right: 65px;
}
.outside-box {
  background: #3c0409;
  border: 1px solid rgba(255, 255, 255, 0.65);
  border-radius: 4px;
  cursor: pointer;
  min-height: 42px;
}
.outside-pill {
  border-radius: 6px;
}
.outside-label {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #ffffff;
}

/* Red & Black Diamonds */
.diamond-icon {
  font-size: 1.8rem;
  line-height: 1;
  display: inline-block;
}
.red-diamond {
  color: #e11d48;
  filter: drop-shadow(0 0 3px rgba(225, 29, 72, 0.8));
}
.black-diamond {
  color: #09090b;
  -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.75);
}

/* Outside hit */
.winning-outside-hit {
  border: 2px solid #fbbf24 !important;
  background: rgba(234, 179, 8, 0.28) !important;
  box-shadow: 0 0 12px rgba(250, 204, 21, 0.7);
}

/* Winning Pocket Cell Highlight */
.winning-cell {
  position: relative;
  z-index: 10;
  border: 2.5px solid #fbbf24 !important;
  box-shadow: inset 0 0 15px #f59e0b, 0 0 20px 4px #fbbf24 !important;
  animation: pulse-winning-cell 1.4s infinite alternate ease-in-out;
}
@keyframes pulse-winning-cell {
  from { box-shadow: inset 0 0 10px #f59e0b, 0 0 12px 2px #fbbf24; }
  to { box-shadow: inset 0 0 18px #f59e0b, 0 0 25px 6px #fef08a; }
}
.winner-indicator {
  position: absolute;
  top: 2px;
  right: 4px;
  font-size: 0.85rem;
}

/* Racetrack & Chip mini icons on right */
.racetrack-icon-btn, .chip-icon-btn {
  width: 48px;
  border: 1px solid rgba(255, 255, 255, 0.35);
  background: rgba(0, 0, 0, 0.4);
  border-radius: 4px;
}
.mini-chip-token {
  display: inline-block;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: radial-gradient(circle, #f43f5e 30%, #881337 100%);
  border: 2px dashed #ffffff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.8);
}

/* Casino Chip Overlay */
.chips-stack {
  position: absolute;
  bottom: 2px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 2px;
  z-index: 5;
  pointer-events: auto;
}
.casino-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 1px 4px;
  border-radius: 12px;
  min-width: 26px;
  height: 18px;
  border: 1.5px dashed #ffffff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.9);
  transition: transform 0.2s;
}
.casino-chip:hover {
  transform: scale(1.35);
  z-index: 20;
}
.chip-win {
  background: radial-gradient(circle, #10b981 0%, #047857 100%);
  border-color: #fef08a;
  color: #ffffff;
  box-shadow: 0 0 8px #10b981;
}
.chip-lost {
  background: radial-gradient(circle, #4b5563 0%, #1f2937 100%);
  border-color: rgba(255, 255, 255, 0.5);
  color: #d1d5db;
  opacity: 0.85;
}

/* Legend chips */
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

/* Dimmed chips on tab filter */
.casino-chip.chip-dimmed {
  opacity: 0.15 !important;
  transform: scale(0.8);
}
</style>

<script>
document.querySelectorAll('.r-tab-filter').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.r-tab-filter').forEach(b => b.classList.remove('active', 'btn-warning'));
    document.querySelectorAll('.r-tab-filter').forEach(b => b.classList.add('btn-outline-warning'));
    this.classList.add('active', 'btn-warning');
    this.classList.remove('btn-outline-warning');

    const selectedTab = this.dataset.tab;
    document.querySelectorAll('.casino-chip').forEach(chip => {
      if (selectedTab === 'all' || chip.dataset.tab === selectedTab) {
        chip.classList.remove('chip-dimmed');
      } else {
        chip.classList.add('chip-dimmed');
      }
    });
  });
});
</script>
