<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsitektur Aplikasi — KurirBS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            min-height: 100vh;
        }

        /* ── Header ── */
        header {
            border-bottom: 1px solid #1e2433;
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #0f1117;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .logo { display: flex; align-items: center; gap: 10px; }
        .logo-dot { width: 10px; height: 10px; border-radius: 50%; background: #E7000B; box-shadow: 0 0 8px #E7000B88; }
        .logo-text { font-size: 15px; font-weight: 700; letter-spacing: .3px; color: #f1f5f9; }
        .logo-sub { font-size: 11px; color: #64748b; font-weight: 400; margin-left: 4px; }

        .btn-brain {
            display: inline-flex; align-items: center; gap: 8px;
            background: #E7000B; color: #fff; font-size: 13px; font-weight: 600;
            padding: 8px 20px; border-radius: 8px; text-decoration: none;
            transition: background .15s, box-shadow .15s;
            box-shadow: 0 0 16px #E7000B44;
        }
        .btn-brain:hover { background: #c0000a; box-shadow: 0 0 24px #E7000B66; }

        /* ── Hero ── */
        .hero {
            text-align: center;
            padding: 64px 32px 48px;
        }
        .hero h1 { font-size: 38px; font-weight: 800; color: #f1f5f9; line-height: 1.2; }
        .hero h1 span { color: #E7000B; }
        .hero p { margin-top: 14px; color: #94a3b8; font-size: 15px; max-width: 560px; margin-left: auto; margin-right: auto; }

        .stats-row {
            display: flex; justify-content: center; gap: 32px;
            margin-top: 36px; flex-wrap: wrap;
        }
        .stat { text-align: center; }
        .stat-num { font-size: 28px; font-weight: 800; color: #f1f5f9; }
        .stat-lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .8px; margin-top: 2px; }

        /* ── Grid ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 32px 64px;
        }

        .card {
            background: #161b27;
            border: 1px solid #1e2433;
            border-radius: 14px;
            padding: 24px;
            transition: border-color .2s, transform .2s;
        }
        .card:hover { border-color: #E7000B44; transform: translateY(-2px); }

        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .card-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .card-title { font-size: 14px; font-weight: 700; color: #f1f5f9; }
        .card-desc { font-size: 12px; color: #64748b; margin-top: 2px; }

        .tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
        .tag {
            font-size: 11px; padding: 3px 10px; border-radius: 20px;
            border: 1px solid; font-weight: 500;
        }

        /* ── Flow diagram ── */
        .flow-section {
            max-width: 1100px; margin: 0 auto 48px; padding: 0 32px;
        }
        .section-title {
            font-size: 13px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: #475569; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #1e2433; }

        .flow {
            display: flex; align-items: center; gap: 0;
            background: #161b27; border: 1px solid #1e2433;
            border-radius: 14px; padding: 28px 24px;
            overflow-x: auto; flex-wrap: nowrap;
        }
        .flow-node {
            display: flex; flex-direction: column; align-items: center;
            min-width: 100px; text-align: center; flex-shrink: 0;
        }
        .flow-bubble {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-bottom: 8px;
            border: 1px solid;
        }
        .flow-label { font-size: 11px; font-weight: 600; color: #cbd5e1; }
        .flow-sub { font-size: 10px; color: #475569; margin-top: 2px; }
        .flow-arrow {
            color: #334155; font-size: 20px; padding: 0 4px;
            flex-shrink: 0; margin-bottom: 20px;
        }

        /* ── Role matrix ── */
        .matrix {
            background: #161b27; border: 1px solid #1e2433; border-radius: 14px;
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead { background: #1a2133; }
        thead th { padding: 12px 16px; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; }
        tbody tr { border-top: 1px solid #1e2433; }
        tbody tr:hover { background: #1a2133; }
        td { padding: 12px 16px; color: #cbd5e1; vertical-align: middle; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; padding: 2px 10px; border-radius: 20px; font-weight: 600;
        }
        .dot { width: 6px; height: 6px; border-radius: 50%; }

        /* Colors */
        .red   { background: #E7000B22; border-color: #E7000B44; color: #E7000B; }
        .blue  { background: #3b82f622; border-color: #3b82f644; color: #60a5fa; }
        .green { background: #22c55e22; border-color: #22c55e44; color: #4ade80; }
        .amber { background: #f59e0b22; border-color: #f59e0b44; color: #fbbf24; }
        .purple{ background: #a855f722; border-color: #a855f744; color: #c084fc; }
        .cyan  { background: #06b6d422; border-color: #06b6d444; color: #22d3ee; }
        .slate { background: #47556922; border-color: #47556944; color: #94a3b8; }

        /* footer */
        footer {
            text-align: center; padding: 24px; font-size: 12px; color: #334155;
            border-top: 1px solid #1e2433;
        }
        footer a { color: #E7000B; text-decoration: none; }
    </style>
</head>
<body>

<!-- Header -->
<header>
    <div class="logo">
        <div class="logo-dot"></div>
        <span class="logo-text">KurirBS <span class="logo-sub">— Arsitektur Aplikasi</span></span>
    </div>
    <a href="/_laravel-brain" class="btn-brain" target="_blank">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
        Buka Interactive Graph
    </a>
</header>

<!-- Hero -->
<section class="hero">
    <h1>Alur <span>Sistem Pengiriman</span><br>KurirBS</h1>
    <p>Gambaran visual arsitektur API Laravel — dari request masuk hingga response keluar, beserta peran, model, dan state machine pengiriman.</p>

    <div class="stats-row">
        <div class="stat"><div class="stat-num">147</div><div class="stat-lbl">Routes</div></div>
        <div class="stat"><div class="stat-num">18</div><div class="stat-lbl">Controllers</div></div>
        <div class="stat"><div class="stat-num">12</div><div class="stat-lbl">Models</div></div>
        <div class="stat"><div class="stat-num">547</div><div class="stat-lbl">Graph Nodes</div></div>
        <div class="stat"><div class="stat-num">1031</div><div class="stat-lbl">Edges</div></div>
    </div>
</section>

<!-- Request Lifecycle Flow -->
<section class="flow-section">
    <div class="section-title">Alur Request API</div>
    <div class="flow">
        <div class="flow-node">
            <div class="flow-bubble blue" style="font-size:20px;">📱</div>
            <div class="flow-label">Client</div>
            <div class="flow-sub">Next.js / Mobile</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble slate">🔒</div>
            <div class="flow-label">Middleware</div>
            <div class="flow-sub">auth:sanctum</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble red">🛡️</div>
            <div class="flow-label">RBAC</div>
            <div class="flow-sub">role / permission</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble amber">⚙️</div>
            <div class="flow-label">Controller</div>
            <div class="flow-sub">Api / v1</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble green">🗄️</div>
            <div class="flow-label">Eloquent</div>
            <div class="flow-sub">Model + DB</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble purple">🔔</div>
            <div class="flow-label">Notification</div>
            <div class="flow-sub">NotificationService</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble cyan">📤</div>
            <div class="flow-label">Response</div>
            <div class="flow-sub">JSON</div>
        </div>
    </div>
</section>

<!-- Shipment State Machine -->
<section class="flow-section">
    <div class="section-title">State Machine — Shipment Level</div>
    <div class="flow">
        <div class="flow-node">
            <div class="flow-bubble amber">⏳</div>
            <div class="flow-label">pending</div>
            <div class="flow-sub">Dibuat User</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble blue">✅</div>
            <div class="flow-label">assigned</div>
            <div class="flow-sub">Driver dipilih</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble purple">🚚</div>
            <div class="flow-label">in_progress</div>
            <div class="flow-sub">Sedang dikirim</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble green">🏁</div>
            <div class="flow-label">completed</div>
            <div class="flow-sub">Selesai</div>
        </div>
        <div class="flow-arrow" style="color:#E7000B44">|</div>
        <div class="flow-node">
            <div class="flow-bubble red">❌</div>
            <div class="flow-label">cancelled</div>
            <div class="flow-sub">Dibatalkan</div>
        </div>
    </div>
</section>

<!-- Destination State Machine -->
<section class="flow-section">
    <div class="section-title">State Machine — Destination Level (per Tujuan)</div>
    <div class="flow">
        <div class="flow-node">
            <div class="flow-bubble slate">📦</div>
            <div class="flow-label">pending</div>
            <div class="flow-sub">Menunggu</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble amber">🤝</div>
            <div class="flow-label">picked</div>
            <div class="flow-sub">Dijemput</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble blue">🚚</div>
            <div class="flow-label">in_progress</div>
            <div class="flow-sub">Di perjalanan</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble cyan">📍</div>
            <div class="flow-label">arrived</div>
            <div class="flow-sub">Sampai lokasi</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble green">✔️</div>
            <div class="flow-label">delivered</div>
            <div class="flow-sub">Diterima</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble purple">🔄</div>
            <div class="flow-label">returning</div>
            <div class="flow-sub">Pulang ke kantor</div>
        </div>
        <div class="flow-arrow">→</div>
        <div class="flow-node">
            <div class="flow-bubble green">🏁</div>
            <div class="flow-label">finished</div>
            <div class="flow-sub">Selesai</div>
        </div>
    </div>
</section>

<!-- Component Cards -->
<section class="flow-section">
    <div class="section-title">Komponen Utama</div>
    <div class="grid" style="padding: 0; max-width: none;">
        <div class="card">
            <div class="card-header">
                <div class="card-icon red">🔑</div>
                <div>
                    <div class="card-title">Authentication</div>
                    <div class="card-desc">Laravel Sanctum — Bearer Token</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag red">auth:sanctum</span>
                <span class="tag slate">POST /login</span>
                <span class="tag slate">POST /logout</span>
                <span class="tag slate">GET /me</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon amber">📦</div>
                <div>
                    <div class="card-title">Shipment</div>
                    <div class="card-desc">CRUD + Bulk assign + State machine</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag amber">ShipmentController</span>
                <span class="tag slate">SPJ-xxx</span>
                <span class="tag blue">bulk-assign-driver</span>
                <span class="tag purple">ShipmentProgressController</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon green">👤</div>
                <div>
                    <div class="card-title">User & Roles</div>
                    <div class="card-desc">Spatie Permission — Admin, Kurir, User</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag green">Admin</span>
                <span class="tag blue">Kurir</span>
                <span class="tag amber">User</span>
                <span class="tag slate">UserController</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon purple">🔔</div>
                <div>
                    <div class="card-title">Notifications</div>
                    <div class="card-desc">DB-based — polling setiap 30 detik</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag purple">NotificationService</span>
                <span class="tag slate">shipment_assigned</span>
                <span class="tag slate">shipment_pending</span>
                <span class="tag red">takeover</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon cyan">📊</div>
                <div>
                    <div class="card-title">Dashboard & Charts</div>
                    <div class="card-desc">DashboardController — role-based data</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag cyan">driver-accumulation</span>
                <span class="tag slate">delivery-trend</span>
                <span class="tag slate">shipment-chart</span>
                <span class="tag green">performance-report</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon blue">🚗</div>
                <div>
                    <div class="card-title">Driver (Kurir)</div>
                    <div class="card-desc">Bulk assignments + Progress tracking</div>
                </div>
            </div>
            <div class="tag-list">
                <span class="tag blue">bulk-assignments</span>
                <span class="tag slate">my-status/toggle</span>
                <span class="tag amber">ShipmentProgress</span>
                <span class="tag red">takeover</span>
            </div>
        </div>
    </div>
</section>

<!-- Role Matrix -->
<section class="flow-section">
    <div class="section-title">Hak Akses per Role</div>
    <div class="matrix">
        <table>
            <thead>
                <tr>
                    <th>Fitur</th>
                    <th>Admin</th>
                    <th>Kurir</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Lihat semua shipment</td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                    <td><span class="badge amber"><span class="dot" style="background:#fbbf24"></span>Milik sendiri</span></td>
                    <td><span class="badge amber"><span class="dot" style="background:#fbbf24"></span>Dibuat sendiri</span></td>
                </tr>
                <tr>
                    <td>Assign driver ke shipment</td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                </tr>
                <tr>
                    <td>Update progress pengiriman</td>
                    <td><span class="badge slate"><span class="dot" style="background:#94a3b8"></span>Terbatas</span></td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                </tr>
                <tr>
                    <td>Buat tiket shipment</td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                </tr>
                <tr>
                    <td>Dashboard analitik</td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Full</span></td>
                    <td><span class="badge amber"><span class="dot" style="background:#fbbf24"></span>Performa sendiri</span></td>
                    <td><span class="badge amber"><span class="dot" style="background:#fbbf24"></span>Shipment sendiri</span></td>
                </tr>
                <tr>
                    <td>Manajemen user / role</td>
                    <td><span class="badge green"><span class="dot" style="background:#4ade80"></span>Ya</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                    <td><span class="badge red"><span class="dot" style="background:#E7000B"></span>Tidak</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<footer>
    Dibuat dengan <a href="/_laravel-brain" target="_blank">Laravel Brain</a> &nbsp;·&nbsp;
    KurirBS API v1 &nbsp;·&nbsp;
    <a href="/_laravel-brain" target="_blank">Buka Interactive Graph →</a>
</footer>

</body>
</html>
