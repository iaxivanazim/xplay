<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // SUCCESS MESSAGE
        @if(session('success'))
        Swal.fire({
            icon: 'success'
            , title: 'Success'
            , text: "{{ session('success') }}"
            , timer: 2000
            , showConfirmButton: false
        });
        @endif

        // ERROR MESSAGE
        @if(session('error'))
        Swal.fire({
            icon: 'error'
            , title: 'Error'
            , text: "{{ session('error') }}"
        });
        @endif

        // VALIDATION ERRORS
        @if($errors -> any())
        Swal.fire({
            icon: 'error'
            , title: 'Validation Error'
            , html: `{!! implode('<br>', $errors->all()) !!}`
        });
        @endif

        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('main-content').classList.toggle('expanded');
            });
        }

    });

</script>



<script>
    function deleteTable(id) {
        Swal.fire({
            title: 'Are you sure?'
            , icon: 'warning'
            , showCancelButton: true
            , confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/game-tables/${id}`)
                    .then(() => {
                        Swal.fire('Deleted!', '', 'success')
                            .then(() => location.reload());
                    });
            }
        });
    }

</script>

<script>
    function deleteTheme(id) {
        Swal.fire({
            title: 'Are you sure?'
            , icon: 'warning'
            , showCancelButton: true
            , confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/themes/${id}`)
                    .then(() => {
                        Swal.fire('Deleted!', '', 'success')
                            .then(() => location.reload());
                    });
            }
        });
    }

</script>

<script>
    function deleteType(id) {
        Swal.fire({
            title: 'Delete Game Type?'
            , icon: 'warning'
            , showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/game-types/${id}`)
                    .then(() => location.reload());
            }
        });
    }

</script>

<script>
    function deleteTheme(id) {
        Swal.fire({
            title: 'Delete Theme?'
            , icon: 'warning'
            , showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/themes/${id}`)
                    .then(() => location.reload());
            }
        });
    }

</script>

<!-- payout rules -->
<script>
    let gameTypeID = null;




    $('.gameTypeBtn').click(function() {

        $('.gameTypeBtn').removeClass('active bg-warning text-dark');

        $(this).addClass('active bg-warning text-dark');

        gameTypeID = $(this).data('id');

        loadRules();

    });



    function loadRules() {

        $.get('/payout_rules/fetch/' + gameTypeID, function(data) {

            let html = '';

            data.forEach(r => {

                html += `

<tr>

<td>

<input class="form-control"
value="${r.bet_name}"
onchange="updateRule(${r.payout_id},this.value,'bet_name')">

</td>


<td>

<input class="form-control"
value="${r.bet_position}"
onchange="updateRule(${r.payout_id},this.value,'bet_position')">

</td>


<td>

<input class="form-control"
value="${r.payout_multiplier}"
onchange="updateRule(${r.payout_id},this.value,'payout_multiplier')">

</td>


<td>

<select
onchange="updateRule(${r.payout_id},this.value,'is_active')"
class="form-control">

<option value="1"
${r.is_active==1?'selected':''}>
Active
</option>

<option value="0"
${r.is_active==0?'selected':''}>
Inactive
</option>

</select>

</td>


<td>

<button
class="btn btn-danger btn-sm"
onclick="deleteRule(${r.payout_id})">
Delete
</button>

</td>

</tr>

`;

            });

            $('#rulesTable').html(html);

        });

    }



    $('#addRuleBtn').click(function() {

        if (!gameTypeID) {
            Swal.fire('Select Game Type First');
            return;
        }

        Swal.fire({

            title: 'Add Payout Rule',

            html: `

<input id="bet_name"
class="swal2-input"
placeholder="Bet Name">

<input id="bet_position"
class="swal2-input"
placeholder="Position">

<input id="multiplier"
class="swal2-input"
placeholder="Multiplier">

`,

            preConfirm: () => {

                $.post('/payout_rules/store', {

                    game_type_id: gameTypeID,

                    bet_name: $('#bet_name').val(),

                    bet_position: $('#bet_position').val(),

                    payout_multiplier: $('#multiplier').val(),

                    is_active: 1,

                    _token: '{{csrf_token()}}'

                }, function() {

                    loadRules();

                });

            }

        });

    });



    function updateRule(id, value, column) {

        $.post('/payout_rules/update/' + id, {

            [column]: value,

            _token: '{{csrf_token()}}'

        });

    }



    function deleteRule(id) {

        Swal.fire({

            title: 'Delete?',

            showCancelButton: true

        }).then(res => {

            if (res.isConfirmed) {

                $.ajax({

                    url: '/payout_rules/delete/' + id,

                    type: 'DELETE',

                    data: {
                        _token: '{{csrf_token()}}'
                    },

                    success: function() {

                        loadRules();

                    }

                });

            }

        });

    }

</script>

<!-- Chips JS -->
<script>
    // Show new preset card
    function newPreset() {
        const card = document.getElementById('newPresetCard');
        card.style.setProperty('display', 'block', 'important');
        card.scrollIntoView({
            behavior: 'smooth'
            , block: 'start'
        });
    }

    // Hide new preset card
    function cancelNew() {
        document.getElementById('newPresetCard').style.setProperty('display', 'none', 'important');
    }

    // Handle all forms (new + existing presets)
    $(document).on('submit', '.chipForm', function(e) {
        e.preventDefault();

        const form = $(this);
        const id = form.data('id');
        const token = form.find('input[name="_token"]').val();

        const data = {
            preset_name: form.find('[name="preset_name"]').val()
            , chip_1_value: form.find('[name="chip1"]').val()
            , chip_2_value: form.find('[name="chip2"]').val()
            , chip_3_value: form.find('[name="chip3"]').val()
            , chip_4_value: form.find('[name="chip4"]').val()
            , chip_5_value: form.find('[name="chip5"]').val()
            , base_value: form.find('[name="base_value"]').val()
            , _token: token
        };

        const url = id ? '/chips/' + id : '/chips';

        $.post(url, data, function(res) {
    Swal.fire({
        icon: 'success',
        title: res.message
    }).then(() => location.reload());
}).fail(function(xhr) {
    if (xhr.status === 403) {
        Swal.fire({
            icon: 'error',
            title: 'Access Denied',
            text: 'You do not have permission to perform this action.'
        }).then(() => location.reload(true));
    }
});
    });

    // Delete a preset (soft delete → status 0)
    function deletePreset(id) {
        if (!id) return;
        Swal.fire({
            title: 'Delete preset?'
            , icon: 'warning'
            , showCancelButton: true
            , confirmButtonColor: '#dc3545'
        }).then((r) => {
            if (r.isConfirmed) {
                $.post('/chips/delete/' + id, {
                    _token: '{{ csrf_token() }}'
                }, function(res) {
                    Swal.fire({
                        icon: 'success'
                        , title: res.message
                        , timer: 1500
                        , showConfirmButton: false
                    }).then(() => location.reload());
                });
            }
        });
    }

    // Restore a preset (status → 1)
    function restorePreset(id) {
        if (!id) return;
        Swal.fire({
            title: 'Restore preset?'
            , icon: 'question'
            , showCancelButton: true
            , confirmButtonColor: '#198754'
        }).then((r) => {
            if (r.isConfirmed) {
                $.post('/chips/restore/' + id, {
                    _token: '{{ csrf_token() }}'
                }, function(res) {
                    Swal.fire({
                        icon: 'success'
                        , title: res.message
                        , timer: 1500
                        , showConfirmButton: false
                    }).then(() => location.reload());
                });
            }
        });
    }

</script>

<script>
    const gameTypeSelect = document.getElementById('gameTypeSelect');
    const chipPresetSelect = document.getElementById('chipPresetSelect');

    // ── Game type change ──────────────────────────────────────────────────
    if (gameTypeSelect) gameTypeSelect.addEventListener('change', function() {
        const code = this.options[this.selectedIndex] ? this.options[this.selectedIndex].dataset.code : null;
console.log('Selected game type code:', code);
        // hide all game field panels
        document.querySelectorAll('.game-fields').forEach(el => el.style.display = 'none');

        // swap burn card label for Andar Bahar
        const burnCardLabel = document.getElementById('burnCardLabel');
        if (burnCardLabel) {
            burnCardLabel.textContent = code === 'AB' ? 'Reset Threshold' : 'Burn Card every round';
        }

        if (code) {
            document.getElementById('gameConfigSection').style.display = 'block';
            document.getElementById('payoutSection').style.display = 'block';

            const panel = document.getElementById('fields-' + code);
            if (panel) panel.style.display = 'block';

            // load payout rules
            console.log('Loading payout rules for game type ID:', this.value);
            loadPayoutRules(this.value);
        } else {
            document.getElementById('gameConfigSection').style.display = 'none';
            document.getElementById('payoutSection').style.display = 'none';
        }
    });

    // ── Chip preset preview ───────────────────────────────────────────────
    if (chipPresetSelect) chipPresetSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const chips = JSON.parse(opt.dataset.chips || '[]');
        const base = opt.dataset.base || '—';

        document.querySelectorAll('.chip-preview-val').forEach((el, i) => {
            el.textContent = chips[i] !== undefined ? chips[i] : '—';
        });
        document.getElementById('basePreview').textContent = base;
    });

    // ── Felt color label ──────────────────────────────────────────────────
    document.getElementById('feltColor').addEventListener('input', function() {
        document.getElementById('feltColorLabel').textContent = this.value;
    });

    // ── Load payout rules via AJAX ────────────────────────────────────────
    function loadPayoutRules(gameTypeId) {
    $.get('/api/v1/payout-rules/' + gameTypeId, function (rules) {
        const tbody = document.getElementById('payoutRulesBody');

        if (!rules || !rules.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-muted">
                        No payout rules defined for this game.
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = rules.map(rule => `
    <tr>
        <td class="text-light">
            ${rule.bet_name}
            ${rule.is_jackpot
                ? `<span class="ms-1 badge"
                          style="background:#1a1200; color:#ffc107;
                                 border:1px solid #ffc10744; font-size:9px;">
                       JACKPOT
                   </span>`
                : ''}
        </td>
        <td class="text-light">${rule.bet_position ?? '—'}</td>
        <td class="text-warning fw-bold">${rule.payout_multiplier ? rule.payout_multiplier + 'x' : '—'}</td>
        <td>
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input payout-toggle"
                       type="checkbox"
                       name="payout_overrides[${rule.payout_id}]"
                       value="1"
                       data-position="${rule.bet_position}"
                       data-jackpot="${rule.is_jackpot ? '1' : '0'}"
                       data-payout-id="${rule.payout_id}"
                       ${rule.is_active && !rule.is_jackpot ? 'checked' : ''}>
            </div>
        </td>
        <td>
            ${rule.is_jackpot
                ? `<input type="number"
                          step="0.01"
                          min="0"
                          name="seed_values[${rule.payout_id}]"
                          id="seed_${rule.payout_id}"
                          class="form-control form-control-sm text-center seed-input"
                          style="background:#111; color:#555; border:1px solid #333;
                                 width:120px; margin:auto; cursor:not-allowed;"
                          placeholder="Enter seed"
                          disabled>`
                : '<span class="text-muted small">—</span>'}
        </td>
    </tr>
`).join('');

// after render
syncB6State();
syncJackpotSeeds();
    }).fail(function () {
        document.getElementById('payoutRulesBody').innerHTML = `
            <tr>
                <td colspan="4" class="text-danger">Failed to load payout rules.</td>
            </tr>`;
    });
}

document.getElementById('tableName')?.addEventListener('input', function () {
    const configName = document.getElementById('configName');
    if (configName) configName.value = this.value;
});

// ── Pipe value preview renderer ───────────────────────────────────────
function renderPipeTags(inputId, previewId, color = '#ffc107') {
    const input   = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;

    input.addEventListener('input', function () {
        const parts = this.value.split('|').map(v => v.trim()).filter(v => v !== '');
        preview.innerHTML = parts.map(v => {
            const isValid = !isNaN(v) && parseFloat(v) > 0;
            return `<span style="
                        font-size:10px; padding:2px 8px; border-radius:10px;
                        background:${isValid ? '#1a1a00' : '#2e1010'};
                        color:${isValid ? color : '#eb5757'};
                        border:1px solid ${isValid ? color + '55' : '#eb5757'};">
                        ${v}
                    </span>`;
        }).join('');
    });
}

renderPipeTags('minBet', 'minBetPreview');
renderPipeTags('maxBet', 'maxBetPreview');

// ── Update min/max pairs in submit validation ─────────────────────────
// Remove old minBet/maxBet from minMaxPairs array since they're now pipe values
// handled separately below
const minMaxPairs = [
    // remove { min: 'minBet', max: 'maxBet', label: 'Bet' } ← delete this line
    { min: 'sideMinBet', max: 'sideMaxBet', label: 'Side Bet'  },
    { min: 'tieMin',     max: 'tieMax',     label: 'Tie'       },
    { min: 'sideMin',    max: 'sideMax',    label: 'Side'      },
    { min: 'pairMin',    max: 'pairMax',    label: 'Pair'      },
    { min: 'hlMin',      max: 'hlMax',      label: 'H/L'       },
];

// ── Pipe min/max cross validation on submit ───────────────────────────
document.getElementById('masterForm')?.addEventListener('submit', function (e) {
    let hasError = false;

    // ── existing minMaxPairs validation (unchanged) ──
    minMaxPairs.forEach(({ min, max, label }) => {
        const minEl = document.getElementById(min);
        const maxEl = document.getElementById(max);
        if (!minEl || !maxEl || (minEl.value === '' && maxEl.value === '')) return;

        const minVal = parseFloat(minEl.value);
        const maxVal = parseFloat(maxEl.value);

        if (minVal <= 0) { setError(minEl, `${label} min must be > 0`); hasError = true; }
        if (maxVal <= 0) { setError(maxEl, `${label} max must be > 0`); hasError = true; }
        else if (maxVal <= minVal) { setError(maxEl, `${label} max must be > min (${minVal})`); hasError = true; }
    });

    // ── Pipe min/max validation ──────────────────────────────────────
    const minBetEl = document.getElementById('minBet');
    const maxBetEl = document.getElementById('maxBet');

    if (minBetEl && maxBetEl) {
        const mins = minBetEl.value.split('|').map(v => v.trim()).filter(v => v);
        const maxs = maxBetEl.value.split('|').map(v => v.trim()).filter(v => v);

        // all values must be numeric and > 0
        const allValid = arr => arr.every(v => !isNaN(v) && parseFloat(v) > 0);

        if (!mins.length || !allValid(mins)) {
            setError(minBetEl, 'Min bet must contain valid numbers separated by |');
            hasError = true;
        } else {
            clearError(minBetEl);
        }

        if (!maxs.length || !allValid(maxs)) {
            setError(maxBetEl, 'Max bet must contain valid numbers separated by |');
            hasError = true;
        } else if (mins.length !== maxs.length) {
            setError(maxBetEl, `Min and Max must have the same number of values (${mins.length} vs ${maxs.length})`);
            hasError = true;
        } else {
            const mismatch = mins.findIndex((m, i) => parseFloat(maxs[i]) <= parseFloat(m));
            if (mismatch !== -1) {
                setError(maxBetEl, `Max #${mismatch + 1} (${maxs[mismatch]}) must be greater than Min (${mins[mismatch]})`);
                hasError = true;
            } else {
                clearError(maxBetEl);
            }
        }
    }

    if (hasError) {
        e.preventDefault();
        const firstError = document.querySelector('.is-invalid');
        if (firstError) { firstError.scrollIntoView({ behavior:'smooth', block:'center' }); firstError.focus(); }
        Swal.fire({ icon:'error', title:'Validation Error', text:'Please fix the highlighted fields.', confirmButtonColor:'#ffc107' });
    }
});

// ── Commission dropdown hint (both banker + B6) ───────────────────────
document.getElementById('b6CommissionSelect')?.addEventListener('change', function () {
    const val = this.value;
    const bankerMult = val == 1 ? '0.95x' : '1x';
    const b6Mult     = val == 1 ? '0.95x' : '0.50x';

    const bankerHint = document.getElementById('bankerMultiplier');
    const b6Hint     = document.getElementById('b6Multiplier');

    if (bankerHint) bankerHint.textContent = bankerMult;
    if (b6Hint)     b6Hint.textContent     = b6Mult;

    // Update table rows
    const bankerToggle = document.querySelector('.payout-toggle[data-position="B"]');
    if (bankerToggle) {
        const bankerCell = bankerToggle.closest('tr').querySelector('.text-warning.fw-bold');
        if (bankerCell) bankerCell.textContent = bankerMult;
    }
    const b6Toggle = document.querySelector('.payout-toggle[data-position="B6"]');
    if (b6Toggle) {
        const b6Cell = b6Toggle.closest('tr').querySelector('.text-warning.fw-bold');
        if (b6Cell) b6Cell.textContent = b6Mult;
    }
});

// ── Sync B6 dropdown state from payout toggle ─────────────────────────
function syncB6State() {
    const b6Toggle = document.querySelector('.payout-toggle[data-position="B6"]');
    if (!b6Toggle) return;

    const isActive = b6Toggle.checked;
    const b6Select = document.getElementById('b6CommissionSelect');
    const b6Badge  = document.getElementById('b6CommissionBadge');

    if (!b6Select) return;

    if (isActive) {
        b6Select.disabled        = false;
        b6Select.style.color     = '#fff';
        b6Select.style.background= '#0a1a0a';
        b6Select.style.cursor    = 'pointer';
        b6Badge.textContent      = 'B6 Active';
        b6Badge.style.background = '#0f2e1a';
        b6Badge.style.color      = '#6fcf97';
        b6Badge.style.border     = '1px solid #6fcf97';

        // update both hints and table values from current dropdown value
        const val = b6Select.value;
        const bankerMult = val == 1 ? '0.95x' : '1x';
        const b6Mult     = val == 1 ? '0.95x' : '0.50x';

        if (document.getElementById('bankerMultiplier')) {
            document.getElementById('bankerMultiplier').textContent = bankerMult;
        }
        if (document.getElementById('b6Multiplier')) {
            document.getElementById('b6Multiplier').textContent = b6Mult;
        }

        const bankerToggle = document.querySelector('.payout-toggle[data-position="B"]');
        if (bankerToggle) {
            const bankerRowMultCell = bankerToggle.closest('tr').querySelector('.text-warning.fw-bold');
            if (bankerRowMultCell) bankerRowMultCell.textContent = bankerMult;
        }
        const b6RowMultCell = b6Toggle.closest('tr').querySelector('.text-warning.fw-bold');
        if (b6RowMultCell) b6RowMultCell.textContent = b6Mult;
    } else {
        b6Select.disabled        = true;
        b6Select.style.color     = '#555';
        b6Select.style.background= '#111';
        b6Select.style.cursor    = 'not-allowed';
        b6Badge.textContent      = 'B6 Inactive';
        b6Badge.style.background = '#2e1010';
        b6Badge.style.color      = '#eb5757';
        b6Badge.style.border     = '1px solid #eb5757';

        // reset hints
        if (document.getElementById('bankerMultiplier')) {
            document.getElementById('bankerMultiplier').textContent = '—';
        }
        if (document.getElementById('b6Multiplier')) {
            document.getElementById('b6Multiplier').textContent = '—';
        }

        // When B6 is inactive, Banker always returns to standard 0.95x commission
        const bankerToggle = document.querySelector('.payout-toggle[data-position="B"]');
        if (bankerToggle) {
            const bankerRowMultCell = bankerToggle.closest('tr').querySelector('.text-warning.fw-bold');
            if (bankerRowMultCell) bankerRowMultCell.textContent = '0.95x';
        }
        // B6 row itself can show its base multiplier or '-' when inactive
        const b6RowMultCell = b6Toggle.closest('tr').querySelector('.text-warning.fw-bold');
        if (b6RowMultCell) b6RowMultCell.textContent = '0.95x'; // or whatever the default is
    }
}

// ── Listen for B6 payout toggle change ───────────────────────────────
// Use event delegation since payout rows may be dynamically rendered


// ── Sync all jackpot seed inputs based on their toggle state ──────────
function syncJackpotSeeds() {
    document.querySelectorAll('.payout-toggle[data-jackpot="1"]').forEach(toggle => {
        enableSeedInput(toggle.dataset.payoutId, toggle.checked);
    });
}

function enableSeedInput(payoutId, isActive) {
    const seedInput = document.getElementById('seed_' + payoutId);
    if (!seedInput) return;

    if (isActive) {
        seedInput.disabled            = false;
        seedInput.style.background    = '#0a1a0a';
        seedInput.style.color         = '#ffc107';
        seedInput.style.border        = '1px solid #ffc10755';
        seedInput.style.cursor        = 'pointer';
    } else {
        seedInput.disabled            = true;
        seedInput.style.background    = '#111';
        seedInput.style.color         = '#555';
        seedInput.style.border        = '1px solid #333';
        seedInput.style.cursor        = 'not-allowed';
        seedInput.value               = ''; // clear when disabled
    }
}

// ── Event delegation — payout toggles ────────────────────────────────
document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('payout-toggle')) return;

    // B6 sync
    if (e.target.dataset.position === 'B6') syncB6State();

    // Jackpot seed sync
    if (e.target.dataset.jackpot === '1') {
        enableSeedInput(e.target.dataset.payoutId, e.target.checked);
    }
});

// ── Init on page load ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    syncB6State();
    syncJackpotSeeds();
});
</script>

{{-- History JS --}}

