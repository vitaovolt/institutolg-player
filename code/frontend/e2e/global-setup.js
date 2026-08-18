import { execSync, spawn } from 'node:child_process'
import http from 'node:http'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

function pidsNaPorta(porta) {
  try {
    const out = execSync('netstat -ano', { encoding: 'utf8' })
    const pids = new Set()
    for (const line of out.split(/\r?\n/)) {
      if (!line.includes(`:${porta}`) || !line.includes('LISTENING')) continue
      const parts = line.trim().split(/\s+/)
      const pid = parts[parts.length - 1]
      if (/^\d+$/.test(pid) && pid !== '0') pids.add(pid)
    }
    return [...pids]
  } catch {
    return []
  }
}

function tentarLiberarPorta(porta) {
  for (let i = 0; i < 8; i += 1) {
    const pids = pidsNaPorta(porta)
    if (pids.length === 0) return true
    for (const pid of pids) {
      try {
        execSync(`taskkill /PID ${pid} /F`, { stdio: 'ignore' })
      } catch {
        // já encerrou ou acesso negado (php de outra sessão)
      }
    }
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 500)
  }

  return pidsNaPorta(porta).length === 0
}

function aguardarHealth(url, tentativas = 80) {
  return new Promise((resolve, reject) => {
    let n = 0
    const tick = () => {
      n += 1
      const req = http.get(url, (res) => {
        res.resume()
        if (res.statusCode && res.statusCode < 500) {
          resolve()
          return
        }
        if (n >= tentativas) reject(new Error(`health falhou: ${url}`))
        else setTimeout(tick, 250)
      })
      req.on('error', () => {
        if (n >= tentativas) reject(new Error(`health inacessível: ${url}`))
        else setTimeout(tick, 250)
      })
    }
    tick()
  })
}

export default async function globalSetup() {
  const backend = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../backend')
  const livrouPorta = tentarLiberarPorta(8000)
  execSync('php artisan migrate:fresh --seed --force', { cwd: backend, stdio: 'inherit' })
  execSync('php artisan cache:clear --quiet', { cwd: backend, stdio: 'inherit' })

  if (livrouPorta) {
    const serve = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], {
      cwd: backend,
      detached: true,
      stdio: 'ignore',
      windowsHide: true,
    })
    serve.unref()
  }

  const worker = spawn('php', ['artisan', 'queue:work', '--queue=biblioteca', '--sleep=1', '--tries=3', '--timeout=120'], {
    cwd: backend,
    detached: true,
    stdio: 'ignore',
    windowsHide: true,
  })
  worker.unref()

  await aguardarHealth('http://127.0.0.1:8000/api/v1/health')
}
