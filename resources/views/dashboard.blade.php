<x-app-layout>

<style>
/* ── Winner seat – high balance (red) animations ──────────────────────── */

/* Three expanding ripple rings that cascade outward and fade */
.winner-ring {
    transform-box: fill-box;
    transform-origin: center;
    animation: winner-ripple 1.5s ease-out infinite;
}
.winner-r2 { animation-delay: .5s;  }
.winner-r3 { animation-delay: 1s; }

@keyframes winner-ripple {
    0%   { transform: scale(1);    opacity: .9; }
    100% { transform: scale(2.4);  opacity: 0;  }
}

/* Slowly rotating dashed accent ring */
.winner-spin {
    transform-box: fill-box;
    transform-origin: center;
    animation: winner-rotate 2.8s linear infinite;
}

@keyframes winner-rotate {
    from { transform: rotate(0deg);   }
    to   { transform: rotate(360deg); }
}

/* Seat ring itself: glowing heartbeat with colour oscillation */
.seat-ring.winner-active {
    animation: winner-glow .85s ease-in-out infinite;
}

@keyframes winner-glow {
    0%, 100% {
        stroke-width: 2;
        stroke-opacity: .85;
        fill-opacity: .15;
        filter: drop-shadow(0 0 4px #fc8181);
    }
    50% {
        stroke-width: 4;
        stroke-opacity: 1;
        fill-opacity: .35;
        filter: drop-shadow(0 0 14px #fc8181) drop-shadow(0 0 28px rgba(252,129,129,.45));
    }
}
</style>

<div class="dash-wrap" id="dashboardRoot">

    <div class="dash-header">
        <div>
            <div class="dash-title"><i class="bi bi-broadcast me-2"></i>Casino Floor Monitor</div>
            <div class="dash-subtitle">Real-time table activity &amp; financial overview</div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="gameday-chip"><i class="bi bi-calendar3"></i><span id="gamedayLabel">&mdash;</span></div>
            <div class="live-badge"><span class="live-dot"></span>LIVE</div>
            <div class="refresh-controls">
                <div class="countdown-ring">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Refresh in</span>
                    <span id="refresh-countdown">&mdash;</span>s
                </div>
                <button class="refresh-btn" id="refreshBtn" onclick="fetchDashboard(true)">
                    <i class="bi bi-arrow-clockwise" id="refreshIcon"></i> Refresh
                </button>
                <select class="interval-select" id="intervalSelect" onchange="setRefreshInterval(this.value)">
                    <option value="10">10s</option>
                    <option value="20" selected>20s</option>
                    <option value="30">30s</option>
                    <option value="60">60s</option>
                </select>
            </div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="--accent-color:#f0c040;--icon-bg:rgba(240,192,64,.1)">
            <div class="kpi-icon"><i class="bi bi-table"></i></div>
            <div class="kpi-label">Open Tables</div>
            <div class="kpi-value" id="kpi-open-tables">&mdash;</div>
            <div class="kpi-sub" id="kpi-total-tables">of &mdash; configured</div>
        </div>
        <div class="kpi-card" style="--accent-color:#68d391;--icon-bg:rgba(56,161,105,.1)">
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="kpi-label">Total Float</div>
            <div class="kpi-value" id="kpi-total-float">&mdash;</div>
            <div class="kpi-sub" id="kpi-float-sub">live across all tables</div>
        </div>
        <div class="kpi-card" style="--accent-color:#90cdf4;--icon-bg:rgba(49,130,206,.1)">
            <div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div>
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value" id="kpi-total-txns">&mdash;</div>
            <div class="kpi-sub">today's gameday</div>
        </div>
        <div class="kpi-card" style="--accent-color:#fbd38d;--icon-bg:rgba(237,137,54,.1)">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div class="kpi-label">Total Buy-ins</div>
            <div class="kpi-value" id="kpi-total-buyins">&mdash;</div>
            <div class="kpi-sub">today</div>
        </div>
        <div class="kpi-card" style="--accent-color:#b794f4;--icon-bg:rgba(128,90,213,.1)">
            <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-label">Net Revenue</div>
            <div class="kpi-value" id="kpi-revenue">&mdash;</div>
            <div class="kpi-sub" id="kpi-revenue-sub">live across open tables</div>
        </div>
    </div>

    <div class="section-label"><i class="bi bi-suit-diamond-fill me-1"></i> Open Tables &mdash; Live Activity</div>
    <div class="tables-grid" id="openTablesGrid">
        <div class="table-card">
            <div class="card-header-band">
                <div class="skeleton" style="width:130px;height:18px;"></div>
                <div class="skeleton" style="width:55px;height:18px;border-radius:20px;"></div>
            </div>
            <div class="casino-table-wrap" style="padding:20px;">
                <div class="skeleton" style="width:260px;height:155px;border-radius:50% 50% 8px 8px;margin:auto;"></div>
            </div>
            <div class="table-stats-bar">
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
                <div class="stat-cell"><div class="skeleton" style="height:32px;"></div></div>
            </div>
            <div class="activity-feed"><div class="feed-empty">Loading&hellip;</div></div>
        </div>
    </div>

    <div id="closedTablesSection" style="display:none;">
        <div class="section-label"><i class="bi bi-moon-stars me-1"></i> Closed / Inactive Tables</div>
        <div id="closedTablesWrap"></div>
    </div>

    <div class="bottom-row">
        <div class="chart-card">
            <h6><i class="bi bi-bar-chart-fill"></i>Hourly Transaction Volume &mdash; Today</h6>
            <div class="bar-chart-wrap" id="hourlyChart">
                <div class="feed-empty" style="width:100%;">Loading chart&hellip;</div>
            </div>
        </div>
        <div class="pending-card">
            <h6>
                <i class="bi bi-clock-history"></i>
                Pending Transactions
                <span class="pending-badge ms-auto" id="pendingBadge">0</span>
            </h6>
            <div id="pendingList"><div class="pending-empty">No pending transactions.</div></div>
        </div>
    </div>

    <div class="player-tooltip" id="playerTooltip"></div>

</div>

@push('scripts')
<script>
(function(){
    'use strict';
    var LIVE_URL = "{{ route('dashboard.live-data') }}";
    var intervalSec = 20, countdown = 20, tickerId = null;

    function fmt(n){ return new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n||0); }
    function fmtI(n){ return new Intl.NumberFormat('en-US').format(n||0); }

    function seatPositions(cx,cy,rx,ry){
        var s=[];
        for(var i=0;i<6;i++){
            var a=Math.PI-(Math.PI/5)*i;
            s.push({x:cx+rx*Math.cos(a),y:cy-ry*Math.sin(a)});
        }
        return s;
    }

    function lighten(hex,amt){
        try{
            var n=parseInt(hex.replace('#',''),16);
            var r=Math.min(255,(n>>16)+amt);
            var g=Math.min(255,((n>>8)&0xff)+amt);
            var b=Math.min(255,(n&0xff)+amt);
            return 'rgb('+r+','+g+','+b+')';
        }catch(e){return hex;}
    }

    function buildSVG(t){
        var W=310,H=215,cx=155,cy=172,rx=122,ry=115;
        var fc=t.felt_color||'#1a5c2e';
        var players=t.players||[];
        var isOpen=t.is_open;
        var seats=seatPositions(cx,cy,rx,ry);
        var uid=t.id;
        var sh='';

        seats.forEach(function(pos,i){
            var p=players[i]||{active:false};
            var active=p.active&&isOpen;
            var sn=i+1;
            var lbl=active?(p.tab_id?p.tab_id.slice(-5):'P'+sn):'Empty';
            var ico=active?'\uD83D\uDC64':'\u25CC';
            var tab=(p.tab_id||'').replace(/"/g,'&quot;');
            var bal=p.balance!=null?p.balance:'';
            var act=(p.last_action||'').replace(/"/g,'&quot;');
            var amnt=p.last_amount!=null?p.last_amount:'';
            var at=(p.last_at||'').replace(/"/g,'&quot;');
            // Balance-based colour thresholds (active seats only)
            // < 200,000 → green | 200,000–400,000 → yellow | > 400,000 → red
            var sc,sf,lc;
            if(active){
                var bv=p.balance!=null?parseFloat(p.balance):0;
                if(bv<200000){
                    sc='#68d391'; sf='rgba(56,161,105,.2)'; lc='#68d391';   // green
                } else if(bv<=400000){
                    sc='#f0c040'; sf='rgba(240,192,64,.2)';  lc='#f0c040';  // yellow
                } else {
                    sc='#fc8181'; sf='rgba(245,101,101,.2)'; lc='#fc8181';  // red
                }
            } else {
                sc='#445566'; sf='rgba(30,40,60,.6)'; lc='#556677';
            }
            var isWinner=active&&sc==='#fc8181';
            var cx2=pos.x.toFixed(1),cy2=pos.y.toFixed(1);
            // Winner (red) seat: triple cascading rings + rotating dashed accent
            // All other active seats: single standard pulse ring
            var pulse='';
            if(isWinner){
                pulse='<circle class="winner-ring"    cx="'+cx2+'" cy="'+cy2+'" r="19" fill="none" stroke="#fc8181" stroke-width="2.5"/>'
                     +'<circle class="winner-ring winner-r2" cx="'+cx2+'" cy="'+cy2+'" r="19" fill="none" stroke="#fc8181" stroke-width="2.5"/>'
                     +'<circle class="winner-ring winner-r3" cx="'+cx2+'" cy="'+cy2+'" r="19" fill="none" stroke="#fc8181" stroke-width="2.5"/>'
                     +'<circle class="winner-spin" cx="'+cx2+'" cy="'+cy2+'" r="22" fill="none" stroke="#fc8181" stroke-width="1.5" stroke-dasharray="5 3" opacity="0.55"/>';
            } else if(active){
                pulse='<circle class="pulse-ring" cx="'+cx2+'" cy="'+cy2+'" r="19" fill="none" stroke="'+sc+'" stroke-width="1.5" opacity="0.5" style="animation-delay:'+(i*0.35)+'s"/>';
            }
            var seatCls='seat-ring '+(active?'active':'inactive')+(isWinner?' winner-active':'');
            sh+='<g class="player-seat" data-seat="'+sn+'" data-active="'+(active?1:0)+'" data-tab="'+tab+'" data-balance="'+bal+'" data-action="'+act+'" data-amount="'+amnt+'" data-at="'+at+'" onmouseenter="dashTTShow(event,this)" onmouseleave="dashTTHide()">';
            sh+=pulse;
            sh+='<circle class="'+seatCls+'" cx="'+cx2+'" cy="'+cy2+'" r="17" style="stroke:'+sc+';fill:'+sf+';"/>';
            sh+='<text x="'+cx2+'" y="'+(pos.y+1).toFixed(1)+'" font-size="13" text-anchor="middle" dominant-baseline="middle">'+ico+'</text>';
            sh+='<text x="'+cx2+'" y="'+(pos.y+27).toFixed(1)+'" font-size="9" fill="'+lc+'" text-anchor="middle" dominant-baseline="middle" font-family="Outfit,sans-serif" font-weight="600">'+lbl+'</text>';
        });


        var dx=cx,dy=cy-22;
        var glow=isOpen?('<path d="M '+(cx-rx)+' '+cy+' A '+rx+' '+ry+' 0 0 1 '+(cx+rx)+' '+cy+'" fill="none" stroke="#68d391" stroke-width="2.5" opacity="0.45"/>'):'';

        return '<svg class="casino-table-svg" viewBox="0 0 '+W+' '+H+'" xmlns="http://www.w3.org/2000/svg">'
            +'<defs>'
            +'<radialGradient id="fg'+uid+'" cx="50%" cy="70%" r="60%"><stop offset="0%" stop-color="'+lighten(fc,18)+'"/><stop offset="100%" stop-color="'+fc+'"/></radialGradient>'
            +'<radialGradient id="bg'+uid+'" cx="50%" cy="60%" r="70%"><stop offset="0%" stop-color="'+fc+'" stop-opacity="0.18"/><stop offset="100%" stop-color="'+fc+'" stop-opacity="0.04"/></radialGradient>'
            +'</defs>'
            +'<rect width="'+W+'" height="'+H+'" fill="url(#bg'+uid+')" rx="12"/>'
            +'<ellipse cx="'+cx+'" cy="'+(cy+8)+'" rx="'+(rx+6)+'" ry="18" fill="rgba(0,0,0,.45)"/>'
            +'<path d="M '+(cx-rx-8)+' '+cy+' A '+(rx+8)+' '+(ry+8)+' 0 0 1 '+(cx+rx+8)+' '+cy+' L '+(cx+rx+8)+' '+(cy+38)+' L '+(cx-rx-8)+' '+(cy+38)+' Z" fill="#7a5600"/>'
            +'<path d="M '+(cx-rx)+' '+cy+' A '+rx+' '+ry+' 0 0 1 '+(cx+rx)+' '+cy+' L '+(cx+rx)+' '+(cy+32)+' L '+(cx-rx)+' '+(cy+32)+' Z" fill="url(#fg'+uid+')"/>'
            +'<path d="M '+(cx-rx+5)+' '+(cy+10)+' Q '+cx+' '+(cy-22)+' '+(cx+rx-5)+' '+(cy+10)+'" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="1"/>'
            +'<path d="M '+(cx-rx+20)+' '+(cy+4)+' Q '+cx+' '+(cy-44)+' '+(cx+rx-20)+' '+(cy+4)+'" fill="none" stroke="rgba(255,255,255,.04)" stroke-width="1"/>'
            +'<ellipse cx="'+cx+'" cy="'+(cy+8)+'" rx="62" ry="18" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1.5" stroke-dasharray="4 4"/>'
            +'<text x="'+cx+'" y="'+(cy+8)+'" text-anchor="middle" dominant-baseline="middle" font-family="Orbitron,sans-serif" font-size="11" font-weight="700" fill="rgba(255,255,255,.25)" letter-spacing="2">'+t.game_code+'</text>'
            +'<circle cx="'+dx+'" cy="'+dy+'" r="16" fill="#0c1c0a" stroke="#f0c040" stroke-width="2"/>'
            +'<text x="'+dx+'" y="'+(dy-1)+'" text-anchor="middle" dominant-baseline="middle" font-size="12">\uD83C\uDFA9</text>'
            +'<text x="'+dx+'" y="'+(dy+16)+'" text-anchor="middle" dominant-baseline="middle" font-family="Outfit,sans-serif" font-size="8" fill="#f0c040" font-weight="700">DEALER</text>'
            +sh+glow+'</svg>';
    }

    function buildCard(t){
        var el=document.createElement('div');
        el.className='table-card '+(t.is_open?'is-open':'is-closed');
        el.id='tc-'+t.id;
        var fc=t.is_open?'#68d391':'#8899aa';
        var rh='';
        var allowedTypes=['BUYIN','DROP','CASHOUT'];
        var visibleTxns=t.recent_txns?t.recent_txns.filter(function(tx){
            var dtype=tx.display_type||tx.txn_type;
            return allowedTypes.indexOf(dtype)!==-1;
        }):[];
        if(visibleTxns.length){
            visibleTxns.forEach(function(tx){
                var dtype=tx.display_type||tx.txn_type;
                // CASHOUT and DROP are always outgoing (chips/cash leave the table).
                // Use type semantics for the sign, not the raw stored amount value,
                // because CASHOUT may be stored as a negative number (e.g. -20000).
                var out=dtype==='DROP'||dtype==='CASHOUT';
                var absAmt=Math.abs(tx.amount||0);
                var sign=out?'-':'+';
                var amtClr=out?'#fc8181':'#68d391';
                rh+='<div class="feed-row">'
                    +'<span class="txn-pill txn-'+dtype+'">'+dtype+'</span>'
                    +'<span style="color:#8899aa;overflow:hidden;max-width:70px;text-overflow:ellipsis;">'+(tx.tab_id?tx.tab_id.slice(-7):'Table')+'</span>'
                    +'<span style="color:#667788;font-size:.68rem;">'+(tx.at||'')+'</span>'
                    +'<span class="feed-amount" style="color:'+amtClr+';">'+sign+fmt(absAmt)+'</span>'
                    +'</div>';
            });
        } else { rh='<div class="feed-empty">No transactions today.</div>'; }
        var ap=t.active_players||0;
        var oa=t.opened_at?'Opened '+t.opened_at:'';
        el.innerHTML='<div class="card-header-band"><div><div class="card-table-name">'+t.name+'</div>'+(oa?'<div style="font-size:.67rem;color:#556677;margin-top:2px;">'+oa+'</div>':'')+'</div>'
            +'<div class="d-flex align-items-center gap-2"><span class="card-game-badge">'+t.game_code+'</span>'
            +'<span class="status-pill '+(t.is_open?'open':'closed')+'">'+(t.is_open?'<span class="live-dot"></span>':'')+(t.is_open?'Open':'Closed')+'</span></div></div>'
            +'<div class="casino-table-wrap">'+buildSVG(t)+'</div>'
            +(function(){
                var floatClr=(t.is_open&&t.float_current!=null&&t.float_current<t.float_open)?'#fc8181':'#68d391';
                return '<div class="table-stats-bar">'
                +'<div class="stat-cell"><div class="sc-label">Float</div><div class="sc-val" style="color:'+floatClr+';">'
                +(t.is_open&&t.stat_float!=null?fmt(t.stat_float):'&mdash;')
                +'</div>'
                +'<div class="sc-sub" style="display:flex;flex-direction:column;gap:1px;">'
                +'<span>Open: '+(t.is_open&&t.float_open!=null?fmt(t.float_open):'&mdash;')+'</span>'
                +'<span>Current: '+(t.is_open&&t.float_current!=null?fmt(t.float_current):'&mdash;')+'</span>'
                +'</div></div>'
                +'<div class="stat-cell"><div class="sc-label">Buy-in</div><div class="sc-val" style="color:#fbd38d;">'+fmt(t.total_buyin)+'</div><div class="sc-sub">cash &amp; chips</div></div>'
                +'<div class="stat-cell"><div class="sc-label">Drop</div><div class="sc-val" style="color:#90cdf4;">'+fmt(t.total_drop)+'</div><div class="sc-sub">cash only</div></div>'
                +'<div class="stat-cell"><div class="sc-label">Cash Out</div><div class="sc-val" style="color:#fc8181;">'+fmt(t.total_cashout)+'</div><div class="sc-sub">total cashout</div></div>'
                +'<div class="stat-cell"><div class="sc-label">Fills</div><div class="sc-val" style="color:#b794f4;">'+fmt(t.total_fill)+'</div><div class="sc-sub">total fills</div></div>'
                +'<div class="stat-cell"><div class="sc-label">Credits</div><div class="sc-val" style="color:#f6ad55;">'+fmt(t.total_credit)+'</div><div class="sc-sub">total credits</div></div>'
                +'</div>';
            })()
            +'<div class="activity-feed"><div class="feed-title"><i class="bi bi-activity me-1"></i>Recent Activity'+(ap>0?' <span style="color:#68d391;font-size:.68rem;margin-left:8px;">&middot; '+ap+' active player'+(ap!==1?'s':'')+'</span>':'')+'</div>'+rh+'</div>';
        return el;
    }

    function renderTables(data){
        var og=document.getElementById('openTablesGrid');
        var cw=document.getElementById('closedTablesWrap');
        var cs=document.getElementById('closedTablesSection');
        var open=data.tables.filter(function(t){return t.is_open;});
        var closed=data.tables.filter(function(t){return !t.is_open;});
        og.innerHTML='';
        if(!open.length){
            og.innerHTML='<div style="color:var(--txt-muted);font-size:.85rem;padding:32px 0;grid-column:1/-1;text-align:center;"><i class="bi bi-moon-stars" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.35;"></i>No tables are currently open.</div>';
        } else {
            open.forEach(function(t){og.appendChild(buildCard(t));});
        }
        cw.innerHTML='';
        if(closed.length){
            cs.style.display='';
            closed.forEach(function(t){
                var r=document.createElement('div');
                r.className='closed-table-row';
                r.innerHTML='<span class="card-game-badge">'+t.game_code+'</span><span style="font-weight:600;">'+t.name+'</span><span style="color:var(--txt-muted);font-size:.72rem;margin-left:auto;">'+fmtI(t.txn_count)+' txns &middot; Buy-in '+fmt(t.total_buyin)+'</span><span class="status-pill closed">Closed</span>';
                cw.appendChild(r);
            });
        } else { cs.style.display='none'; }
    }

    function renderKPIs(k,gd){
        document.getElementById('kpi-open-tables').textContent=k.open_tables;
        document.getElementById('kpi-total-tables').textContent='of '+k.total_tables+' configured';

        // Total Float: main = live (current open sessions), sub = gameday total
        document.getElementById('kpi-total-float').textContent=fmt(k.total_float);
        document.getElementById('kpi-float-sub').innerHTML=
            'Live &nbsp;&middot;&nbsp; Day: <strong>'+fmt(k.total_float_day)+'</strong>';

        document.getElementById('kpi-total-txns').textContent=fmtI(k.total_txns);
        document.getElementById('kpi-total-buyins').textContent=fmt(k.total_buyins);

        // Net Revenue: main = live (open sessions), sub = gameday total (coloured)
        var re=document.getElementById('kpi-revenue');
        var reSub=document.getElementById('kpi-revenue-sub');
        if(k.total_revenue===null||k.total_revenue===undefined){
            re.textContent='\u2014';
            re.style.color='';
        } else {
            re.textContent=fmt(k.total_revenue);
            re.style.color=k.total_revenue>=0?'#68d391':'#fc8181';
        }
        var dayRevClr=(k.total_revenue_day!=null&&k.total_revenue_day<0)?'#fc8181':'#68d391';
        reSub.innerHTML='Live &nbsp;&middot;&nbsp; Day: <strong style="color:'+dayRevClr+';">'+
            (k.total_revenue_day!=null?fmt(k.total_revenue_day):'\u2014')+'</strong>';

        document.getElementById('gamedayLabel').textContent=gd;
        document.getElementById('pendingBadge').textContent=k.pending_txns||0;
    }

    function renderChart(hv){
        var ch=document.getElementById('hourlyChart');
        ch.innerHTML='';
        var vals=[];
        for(var h=0;h<24;h++){if(hv[h])vals.push({h:h,c:hv[h].count});}
        if(!vals.length){ch.innerHTML='<div class="feed-empty" style="width:100%;">No transactions recorded yet today.</div>';return;}
        var mx=Math.max.apply(null,vals.map(function(v){return v.c;}).concat([1]));
        vals.forEach(function(v){
            var col=document.createElement('div');
            col.className='bar-col';
            var bh=Math.max(2,Math.round((v.c/mx)*88));
            var lb=v.h<10?'0'+v.h:''+v.h;
            col.innerHTML='<div class="bar-fill" style="height:'+bh+'px;" title="'+v.c+' txns at '+lb+':00"></div><div class="bar-hour">'+lb+'</div>';
            ch.appendChild(col);
        });
    }

    window.dashTTShow=function(e,el){
        var tt=document.getElementById('playerTooltip');
        var active=el.getAttribute('data-active')==='1';
        var seat=el.getAttribute('data-seat');
        var tab=el.getAttribute('data-tab');
        var bal=el.getAttribute('data-balance');
        var act=el.getAttribute('data-action');
        var amt=el.getAttribute('data-amount');
        var at=el.getAttribute('data-at');
        if(active){
            tt.innerHTML='<div class="tt-seat">Seat '+seat+'</div>'
                +'<div class="tt-row"><span class="tt-key">Tab ID</span><span class="tt-val">'+(tab||'&mdash;')+'</span></div>'
                +'<div class="tt-row"><span class="tt-key">Balance</span><span class="tt-val">'+(bal!==''?fmt(parseFloat(bal)):'&mdash;')+'</span></div>'
                +'<div class="tt-row"><span class="tt-key">Last Action</span><span class="tt-val">'+(act||'&mdash;')+'</span></div>'
                +'<div class="tt-row"><span class="tt-key">Amount</span><span class="tt-val">'+(amt!==''?fmt(parseFloat(amt)):'&mdash;')+'</span></div>'
                +'<div class="tt-row"><span class="tt-key">At</span><span class="tt-val">'+(at||'&mdash;')+'</span></div>';
        } else {
            tt.innerHTML='<div class="tt-seat">Seat '+seat+'</div><div style="color:var(--txt-muted);margin-top:6px;font-size:.72rem;">Empty seat</div>';
        }
        tt.classList.add('visible');
        document.addEventListener('mousemove',dashTTMove);
    };
    window.dashTTHide=function(){
        document.getElementById('playerTooltip').classList.remove('visible');
        document.removeEventListener('mousemove',dashTTMove);
    };
    function dashTTMove(e){
        var tt=document.getElementById('playerTooltip');
        tt.style.left=(e.clientX+16)+'px';
        tt.style.top=(e.clientY-8)+'px';
    }

    async function fetchDashboard(manual){
        if(manual){
            var btn=document.getElementById('refreshBtn');
            btn.classList.add('spinning');
            setTimeout(function(){btn.classList.remove('spinning');},700);
        }
        try{
            var res=await fetch(LIVE_URL,{headers:{'X-Requested-With':'XMLHttpRequest'}});
            var data=await res.json();
            if(!data.success)return;
            renderKPIs(data.kpis,data.gameday);
            renderTables(data);
            renderChart(data.hourly_volume||{});
        }catch(err){console.error('Dashboard fetch error:',err);}
        countdown=intervalSec;
    }
    window.fetchDashboard=fetchDashboard;

    function startTicker(){
        if(tickerId)clearInterval(tickerId);
        countdown=intervalSec;
        tickerId=setInterval(function(){
            countdown--;
            var el=document.getElementById('refresh-countdown');
            if(el)el.textContent=countdown;
            if(countdown<=0)fetchDashboard(false);
        },1000);
    }

    window.setRefreshInterval=function(sec){
        intervalSec=parseInt(sec);
        startTicker();
    };

    document.addEventListener('DOMContentLoaded',function(){
        var el=document.getElementById('refresh-countdown');
        if(el)el.textContent=intervalSec;
        fetchDashboard(false);
        startTicker();
    });
})();
</script>
@endpush

</x-app-layout>