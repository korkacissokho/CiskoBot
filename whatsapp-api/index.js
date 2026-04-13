/**
 * ╔═══════════════════════════════════════════════════════════╗
 * ║       Cissokho — WhatsApp API (Baileys)                  ║
 * ║  Démarrer :  node index.js                               ║
 * ║  Scanner QR : http://localhost:3000/qr                   ║
 * ╚═══════════════════════════════════════════════════════════╝
 */

import express         from 'express'
import {
  makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  isJidGroup,
} from '@whiskeysockets/baileys'
import { Boom }        from '@hapi/boom'
import QRCode          from 'qrcode'
import qrcodeTerminal  from 'qrcode-terminal'
import pino            from 'pino'
import fs              from 'fs'
import path            from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// Charger .env s'il existe (local). En production, les vars viennent de la plateforme.
const _envPath = path.join(__dirname, '.env')
if (fs.existsSync(_envPath)) {
  for (const line of fs.readFileSync(_envPath, 'utf8').split('\n')) {
    const trimmed = line.trim()
    if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) continue
    const eq  = trimmed.indexOf('=')
    const key = trimmed.slice(0, eq).trim()
    const val = trimmed.slice(eq + 1).trim()
    if (key && !(key in process.env)) process.env[key] = val
  }
}

// ── Configuration ────────────────────────────────────────────
const PORT        = parseInt(process.env.PORT        || '3000')
const API_TOKEN   = process.env.API_TOKEN            || 'change_this_token'
const WEBHOOK_URL = process.env.WEBHOOK_URL          || ''   // URL du bot PHP
const AUTH_DIR    = path.join(__dirname, 'auth')

// ── Express ──────────────────────────────────────────────────
const app = express()
app.use(express.json({ limit: '100mb' }))
app.use((req, res, next) => {
  res.setHeader('X-Powered-By', 'Cissokho-WA-API')
  next()
})

// ── Middleware auth Bearer ────────────────────────────────────
const requireAuth = (req, res, next) => {
  const auth  = req.headers['authorization'] || ''
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : auth
  if (!token || token !== API_TOKEN) {
    return res.status(401).json({ ok: false, error: 'Token invalide ou manquant' })
  }
  next()
}

// ── État global ───────────────────────────────────────────────
let sock       = null
let qrDataURL  = null
let connected  = false
let reconnectTimeout = null

const logger = pino({ level: 'silent' })

// ── Helpers ───────────────────────────────────────────────────
const toJid = (phone) => {
  // Si c'est déjà un JID complet (@s.whatsapp.net, @lid, @g.us…) → l'utiliser tel quel
  if (phone.includes('@')) return phone
  const clean = phone.replace(/\D/g, '')
  return clean + '@s.whatsapp.net'
}

const getMime = (ext) => ({
  pdf:  'application/pdf',
  zip:  'application/zip',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  epub: 'application/epub+zip',
  mp4:  'video/mp4',
  png:  'image/png',
  jpg:  'image/jpeg',
  jpeg: 'image/jpeg',
  gif:  'image/gif',
  txt:  'text/plain',
})[ext] || 'application/octet-stream'

// Forwarder message entrant → PHP webhook
const forwardToWebhook = async (from, text) => {
  if (!WEBHOOK_URL) return
  try {
    const res = await fetch(WEBHOOK_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ from, text }),
      signal:  AbortSignal.timeout(12_000),
    })
    console.log(`[Webhook] → ${from} forwarded (${res.status})`)
  } catch (err) {
    console.error('[Webhook] Erreur:', err.message)
  }
}

// ── Connexion WhatsApp ────────────────────────────────────────
async function connectWhatsApp() {
  if (reconnectTimeout) clearTimeout(reconnectTimeout)

  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR)
  const { version }          = await fetchLatestBaileysVersion()

  console.log(`[WA] Baileys v${version.join('.')} — Connexion...`)

  sock = makeWASocket({
    version,
    auth:               state,
    logger,
    printQRInTerminal:  false,
    browser:            ['Cissokho Bot', 'Chrome', '120.0.0'],
    connectTimeoutMs:   30_000,
    defaultQueryTimeoutMs: 20_000,
    keepAliveIntervalMs: 25_000,
    retryRequestDelayMs: 2_000,
    maxMsgRetryCount:   3,
  })

  // Sauvegarde des credentials
  sock.ev.on('creds.update', saveCreds)

  // Mise à jour de la connexion
  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update

    // QR Code disponible
    if (qr) {
      qrDataURL = await QRCode.toDataURL(qr)
      connected = false
      qrcodeTerminal.generate(qr, { small: true })
      console.log(`\n[WA] Scannez le QR : http://localhost:${PORT}/qr\n`)
    }

    // Déconnexion
    if (connection === 'close') {
      connected = false
      qrDataURL = null
      const code = lastDisconnect?.error instanceof Boom
        ? lastDisconnect.error.output.statusCode
        : 0

      const shouldReconnect = code !== DisconnectReason.loggedOut

      console.log(`[WA] Déconnecté (code ${code}). Reconnexion: ${shouldReconnect}`)

      if (shouldReconnect) {
        reconnectTimeout = setTimeout(connectWhatsApp, 4_000)
      } else {
        console.log('[WA] Déconnecté définitivement. Supprimez le dossier auth/ et redémarrez.')
      }
    }

    // Connecté
    if (connection === 'open') {
      connected = true
      qrDataURL = null
      const me  = sock.user?.id?.split(':')[0] || '?'
      console.log(`[WA] Connecté ! Numéro : ${me}`)
    }
  })

  // Réception des messages entrants
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    // Log de débogage — tous les types
    console.log(`[UPSERT] type=${type} | count=${messages.length}`)

    for (const msg of messages) {
      // Ignorer les messages envoyés par le bot
      if (msg.key.fromMe) continue

      const jid = msg.key.remoteJid || ''
      if (isJidGroup(jid)) continue

      // Garder le JID complet comme identifiant (gère @s.whatsapp.net ET @lid)
      const from = jid

      // Extraction du texte — tous les formats connus
      const text =
        msg.message?.conversation ||
        msg.message?.extendedTextMessage?.text ||
        msg.message?.imageMessage?.caption ||
        msg.message?.videoMessage?.caption ||
        msg.message?.buttonsResponseMessage?.selectedDisplayText ||
        msg.message?.listResponseMessage?.title ||
        msg.message?.templateButtonReplyMessage?.selectedDisplayText ||
        ''

      console.log(`[MSG] type=${type} jid=${from} text="${text.slice(0, 60)}" fromMe=${msg.key.fromMe}`)

      // Accepter notify ET append (certains clients WhatsApp envoient append)
      if (!['notify', 'append'].includes(type)) continue
      if (!from || !text.trim()) continue

      console.log(`[FORWARD] → webhook : +${from} | "${text.trim()}"`)
      await forwardToWebhook(from, text.trim())
    }
  })
}

// ── Routes publiques ──────────────────────────────────────────

// Health check
app.get('/', (req, res) => {
  res.json({
    ok:        true,
    service:   'Cissokho WhatsApp API',
    connected,
    timestamp: new Date().toISOString(),
  })
})

// Page QR Code (HTML)
app.get('/qr', (req, res) => {
  if (connected) {
    return res.send(`
      <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
      <title>WhatsApp API</title>
      <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
      min-height:100vh;background:#f0f4f8;}</style></head>
      <body><div style="text-align:center;background:#fff;padding:40px;border-radius:16px;
      box-shadow:0 4px 24px rgba(0,0,0,.1);">
      <div style="font-size:3rem">✅</div>
      <h2 style="color:#059669">WhatsApp connecté !</h2>
      <p style="color:#64748b">Le bot est opérationnel.</p>
      </div></body></html>
    `)
  }

  if (!qrDataURL) {
    return res.send(`
      <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
      <title>Chargement QR…</title>
      <meta http-equiv="refresh" content="3">
      <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
      min-height:100vh;background:#f0f4f8;}</style></head>
      <body><div style="text-align:center;background:#fff;padding:40px;border-radius:16px;">
      <p>Génération du QR en cours… <br><small>La page se rafraîchit automatiquement.</small></p>
      </div></body></html>
    `)
  }

  res.send(`
    <!DOCTYPE html><html lang="fr">
    <head><meta charset="UTF-8"><title>Scanner le QR — Cissokho</title>
    <meta http-equiv="refresh" content="30">
    <style>
      body{font-family:'Segoe UI',sans-serif;display:flex;align-items:center;
      justify-content:center;min-height:100vh;background:linear-gradient(135deg,#0f172a,#1e3a5f);}
      .box{background:#fff;border-radius:18px;padding:40px;text-align:center;
      box-shadow:0 20px 60px rgba(0,0,0,.3);}
      img{width:260px;height:260px;border:8px solid #25D366;border-radius:12px;}
      h2{color:#0f172a;margin:16px 0 6px}p{color:#64748b;font-size:.9rem}
    </style></head>
    <body>
      <div class="box">
        <h2>Scanner avec WhatsApp</h2>
        <p>Ouvrez WhatsApp → Appareils liés → Lier un appareil</p>
        <img src="${qrDataURL}" alt="QR Code WhatsApp">
        <p style="margin-top:14px;font-size:.8rem;color:#94a3b8">
          Le QR expire en 60 secondes — la page se rafraîchit automatiquement.
        </p>
      </div>
    </body></html>
  `)
})

// ── Routes API (protégées) ────────────────────────────────────

// Statut de connexion
app.get('/api/v1/status', requireAuth, (req, res) => {
  res.json({ ok: true, connected, phone: sock?.user?.id?.split(':')[0] || null })
})

// QR Code en JSON — pour l'admin PHP
app.get('/api/v1/qr', requireAuth, (req, res) => {
  if (connected) {
    return res.json({ ok: true, connected: true, qr: null, phone: sock?.user?.id?.split(':')[0] || null })
  }
  res.json({ ok: true, connected: false, qr: qrDataURL || null })
})

// ── Envoyer un message texte ──────────────────────────────────
app.post('/api/v1/message', requireAuth, async (req, res) => {
  const { to, text } = req.body

  if (!to || !text) {
    return res.status(400).json({ ok: false, error: 'Champs "to" et "text" requis' })
  }
  if (!connected || !sock) {
    return res.status(503).json({ ok: false, error: 'WhatsApp non connecté. Scannez le QR.' })
  }

  try {
    const jid = toJid(to)
    console.log(`[Envoi] → JID=${jid} | "${String(text).slice(0, 40)}…"`)
    await sock.sendMessage(jid, { text: String(text) })
    res.json({ ok: true, message: 'Message envoyé' })
  } catch (err) {
    console.error('[Envoi texte]', err.message)
    res.status(500).json({ ok: false, error: err.message })
  }
})

// ── Envoyer un document (chemin absolu sur le serveur) ────────
app.post('/api/v1/document', requireAuth, async (req, res) => {
  const { to, filePath, filename, caption, mimetype } = req.body

  if (!to || !filePath) {
    return res.status(400).json({ ok: false, error: 'Champs "to" et "filePath" requis' })
  }
  if (!connected || !sock) {
    return res.status(503).json({ ok: false, error: 'WhatsApp non connecté' })
  }
  if (!fs.existsSync(filePath)) {
    return res.status(404).json({ ok: false, error: `Fichier introuvable : ${filePath}` })
  }

  try {
    const jid    = toJid(to)
    const ext    = path.extname(filePath).slice(1).toLowerCase()
    const mime   = mimetype || getMime(ext)
    const fname  = filename || path.basename(filePath)
    const buffer = fs.readFileSync(filePath)

    await sock.sendMessage(jid, {
      document: buffer,
      mimetype: mime,
      fileName: fname,
      caption:  caption || `📄 ${fname}`,
    })

    console.log(`[Envoi doc] → +${to} | ${fname}`)
    res.json({ ok: true, message: 'Document envoyé' })
  } catch (err) {
    console.error('[Envoi document]', err.message)
    res.status(500).json({ ok: false, error: err.message })
  }
})

// ── Envoyer un document par URL ───────────────────────────────
app.post('/api/v1/document-url', requireAuth, async (req, res) => {
  const { to, url, filename, caption, mimetype } = req.body

  if (!to || !url) {
    return res.status(400).json({ ok: false, error: 'Champs "to" et "url" requis' })
  }
  if (!connected || !sock) {
    return res.status(503).json({ ok: false, error: 'WhatsApp non connecté' })
  }

  try {
    console.log(`[Doc-URL] Téléchargement depuis ${url}…`)
    const response = await fetch(url, { signal: AbortSignal.timeout(30_000) })

    if (!response.ok) {
      return res.status(502).json({ ok: false, error: `Impossible de télécharger : HTTP ${response.status}` })
    }

    const buffer = Buffer.from(await response.arrayBuffer())
    const jid    = toJid(to)
    const ext    = (url.split('.').pop().split('?')[0] || 'bin').toLowerCase()
    const mime   = mimetype || getMime(ext)
    const fname  = filename || `document.${ext}`

    await sock.sendMessage(jid, {
      document: buffer,
      mimetype: mime,
      fileName: fname,
      caption:  caption || `📄 ${fname}`,
    })

    console.log(`[Envoi doc-url] → +${to} | ${fname}`)
    res.json({ ok: true, message: 'Document envoyé depuis URL' })
  } catch (err) {
    console.error('[Doc-URL]', err.message)
    res.status(500).json({ ok: false, error: err.message })
  }
})

// ── Envoyer une image ─────────────────────────────────────────
app.post('/api/v1/image', requireAuth, async (req, res) => {
  const { to, filePath, url, caption } = req.body

  if (!to || (!filePath && !url)) {
    return res.status(400).json({ ok: false, error: 'Champs "to" et "filePath" ou "url" requis' })
  }
  if (!connected || !sock) {
    return res.status(503).json({ ok: false, error: 'WhatsApp non connecté' })
  }

  try {
    const jid = toJid(to)
    let buffer

    if (filePath) {
      if (!fs.existsSync(filePath)) throw new Error('Fichier introuvable')
      buffer = fs.readFileSync(filePath)
    } else {
      const r = await fetch(url, { signal: AbortSignal.timeout(20_000) })
      buffer  = Buffer.from(await r.arrayBuffer())
    }

    await sock.sendMessage(jid, { image: buffer, caption: caption || '' })
    res.json({ ok: true, message: 'Image envoyée' })
  } catch (err) {
    console.error('[Envoi image]', err.message)
    res.status(500).json({ ok: false, error: err.message })
  }
})

// ── Déconnecter WhatsApp ──────────────────────────────────────
app.post('/api/v1/logout', requireAuth, async (req, res) => {
  try {
    await sock?.logout()
    connected = false
    // Supprimer la session
    fs.rmSync(AUTH_DIR, { recursive: true, force: true })
    fs.mkdirSync(AUTH_DIR, { recursive: true })
    res.json({ ok: true, message: 'Déconnecté. Redémarrez pour scanner un nouveau QR.' })
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message })
  }
})

// 404
app.use((req, res) => {
  res.status(404).json({ ok: false, error: `Route ${req.method} ${req.path} introuvable` })
})

// ── Démarrage ─────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`
╔══════════════════════════════════════╗
║   Cissokho WhatsApp API démarrée    ║
║   http://localhost:${PORT}               ║
╚══════════════════════════════════════╝
  `)
  connectWhatsApp()
})
