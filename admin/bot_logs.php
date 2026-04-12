<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$logFile = __DIR__ . '/../bot/bot.log';

// Action : vider les logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    file_put_contents($logFile, '');
    header('Location: bot_logs.php?cleared=1');
    exit;
}

// Lire les logs (dernières 200 lignes, ordre inversé)
$lines = [];
if (file_exists($logFile)) {
    $raw   = file($logFile, FILE_IGNORE_NEW_LINES);
    $lines = array_reverse(array_filter($raw, fn($l) => trim($l) !== ''));
}

layout_start('Logs du Bot', 'bot_logs');
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h2 style="margin:0;font-size:1.25rem;font-weight:600;">Activité du bot WhatsApp</h2>
    <p style="margin:.25rem 0 0;color:var(--text-muted);font-size:.875rem;">
      <?= count($lines) ?> entrée<?= count($lines) !== 1 ? 's' : '' ?> — Rafraîchissement automatique toutes les 5 secondes
    </p>
  </div>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <button id="toggleAuto" class="btn btn-secondary" onclick="toggleAutoRefresh()">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      <span id="autoLabel">Pause</span>
    </button>
    <form method="post" onsubmit="return confirm('Vider tous les logs ?');">
      <input type="hidden" name="action" value="clear">
      <button type="submit" class="btn btn-danger">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Vider
      </button>
    </form>
  </div>
</div>

<?php if (isset($_GET['cleared'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem;">Logs vidés avec succès.</div>
<?php endif; ?>

<?php if (empty($lines)): ?>
<div class="card" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;margin:0 auto 1rem;display:block;opacity:.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
  <p style="margin:0;font-size:.9375rem;">Aucune activité enregistrée pour l'instant.</p>
  <p style="margin:.5rem 0 0;font-size:.8125rem;">Envoyez un message WhatsApp au bot pour voir apparaître les logs.</p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
  <div id="logContainer" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:.8125rem;font-family:'Courier New',monospace;">
      <thead>
        <tr style="background:var(--bg-secondary);border-bottom:1px solid var(--border);">
          <th style="padding:.625rem 1rem;text-align:left;font-family:var(--font);font-size:.75rem;font-weight:600;color:var(--text-muted);white-space:nowrap;">Heure</th>
          <th style="padding:.625rem .75rem;text-align:left;font-family:var(--font);font-size:.75rem;font-weight:600;color:var(--text-muted);">Niveau</th>
          <th style="padding:.625rem 1rem;text-align:left;font-family:var(--font);font-size:.75rem;font-weight:600;color:var(--text-muted);">Message</th>
        </tr>
      </thead>
      <tbody id="logBody">
        <?php foreach ($lines as $line):
            // Parse : [dd/mm HH:ii:ss] [LEVEL] message
            preg_match('/^\[([^\]]+)\]\s*\[([^\]]+)\]\s*(.*)$/', $line, $m);
            $time  = $m[1] ?? '';
            $level = $m[2] ?? '';
            $msg   = $m[3] ?? $line;

            $levelColor = match($level) {
                'IN'   => '#3b82f6',
                'OUT'  => '#22c55e',
                'FAIL' => '#ef4444',
                'ERR'  => '#f97316',
                default=> '#94a3b8',
            };
            $levelBg = match($level) {
                'IN'   => 'rgba(59,130,246,.12)',
                'OUT'  => 'rgba(34,197,94,.12)',
                'FAIL' => 'rgba(239,68,68,.12)',
                'ERR'  => 'rgba(249,115,22,.12)',
                default=> 'rgba(148,163,184,.1)',
            };
        ?>
        <tr style="border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background=''">
          <td style="padding:.5rem 1rem;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars($time) ?></td>
          <td style="padding:.5rem .75rem;">
            <span style="display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.6875rem;font-weight:700;letter-spacing:.05em;color:<?= $levelColor ?>;background:<?= $levelBg ?>;font-family:var(--font);">
              <?= htmlspecialchars($level) ?>
            </span>
          </td>
          <td style="padding:.5rem 1rem;color:var(--text-primary);word-break:break-all;"><?= htmlspecialchars($msg) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
let autoRefresh = true;
let timer = null;

function toggleAutoRefresh() {
  autoRefresh = !autoRefresh;
  document.getElementById('autoLabel').textContent = autoRefresh ? 'Pause' : 'Reprendre';
  if (autoRefresh) scheduleRefresh();
  else clearTimeout(timer);
}

async function refreshLogs() {
  try {
    const res  = await fetch('bot_logs_data.php?_=' + Date.now());
    const data = await res.json();
    const body = document.getElementById('logBody');
    if (!body) return;

    const levelColor = { IN:'#3b82f6', OUT:'#22c55e', FAIL:'#ef4444', ERR:'#f97316' };
    const levelBg    = { IN:'rgba(59,130,246,.12)', OUT:'rgba(34,197,94,.12)', FAIL:'rgba(239,68,68,.12)', ERR:'rgba(249,115,22,.12)' };

    body.innerHTML = data.lines.map(l => {
      const m = l.match(/^\[([^\]]+)\]\s*\[([^\]]+)\]\s*(.*)$/);
      const time  = m ? m[1] : '';
      const level = m ? m[2] : '';
      const msg   = m ? m[3] : l;
      const col   = levelColor[level] || '#94a3b8';
      const bg    = levelBg[level]   || 'rgba(148,163,184,.1)';
      return `<tr style="border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background=''">
        <td style="padding:.5rem 1rem;color:var(--text-muted);white-space:nowrap;">${esc(time)}</td>
        <td style="padding:.5rem .75rem;"><span style="display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.6875rem;font-weight:700;letter-spacing:.05em;color:${col};background:${bg};font-family:var(--font);">${esc(level)}</span></td>
        <td style="padding:.5rem 1rem;color:var(--text-primary);word-break:break-all;">${esc(msg)}</td>
      </tr>`;
    }).join('');

    // Mise à jour du compteur
    document.querySelector('.page-header p').innerHTML =
      `${data.count} entrée${data.count !== 1 ? 's' : ''} — Rafraîchissement automatique toutes les 5 secondes`;

  } catch(e) { /* silencieux */ }
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function scheduleRefresh() {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    if (autoRefresh) {
      await refreshLogs();
      scheduleRefresh();
    }
  }, 5000);
}

scheduleRefresh();
</script>

<?php layout_end(); ?>
