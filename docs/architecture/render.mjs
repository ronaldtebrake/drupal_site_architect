/**
 * Render the self-contained explainer, without visiting the site or calling AI.
 * Requires Node 22+, Chromium and FFmpeg. No npm packages or credentials.
 *
 * node render.mjs --check --output /path/to/artifacts
 * node render.mjs --output /path/to/artifacts
 */
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { createHash } from 'node:crypto';

const args = process.argv.slice(2);
const outputArg = args.indexOf('--output');
const output = resolve(outputArg >= 0 ? args[outputArg + 1] : './architecture-export');
const checkOnly = args.includes('--check');
const source = join(dirname(fileURLToPath(import.meta.url)), 'index.html');
const width = 1280, height = 900, fps = 24;
const scratch = await mkdtemp(join(tmpdir(), 'advisor-architecture-'));
await mkdir(output, { recursive: true });
let chrome, ffmpeg, socket;
const runtimeErrors = [], pending = new Map();
let commandId = 0;

function command(method, params = {}) {
  const id = ++commandId;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: ' + method)); }, 30000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}
const evaluateFunction = fn => evaluate('(' + fn.toString() + ')()');
async function imageAt(time) {
  await evaluate('AdvisorAnimation.seek(' + time + ')');
  const shot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, clip: { x: 0, y: 0, width, height, scale: 1 } });
  return Buffer.from(shot.data, 'base64');
}
async function run(program, args) {
  const child = spawn(program, args, { stdio: ['ignore', 'pipe', 'pipe'] });
  let stderr = '', stdout = '';
  child.stderr.on('data', data => stderr += data.toString());
  child.stdout.on('data', data => stdout += data.toString());
  const [code] = await once(child, 'close');
  if (code !== 0) throw new Error(program + ' failed: ' + stderr.slice(-2000));
  return stdout;
}
async function navigate(query) {
  await command('Page.navigate', { url: pathToFileURL(source).href + query });
  for (let i = 0; i < 100; i++) {
    try {
      if (await evaluate('document.readyState === "complete" && Boolean(window.AdvisorAnimation) && location.search === ' + JSON.stringify(query))) return;
    } catch {}
    await delay(40);
  }
  throw new Error('Animation did not load.');
}

try {
  chrome = spawn(process.env.CHROMIUM || 'chromium', [
    '--headless=new', '--no-sandbox', '--disable-dev-shm-usage',
    '--no-first-run', '--no-default-browser-check', '--hide-scrollbars',
    '--disable-background-networking', '--disable-extensions',
    '--disable-component-update', '--disable-sync',
    '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1',
    '--user-data-dir=' + join(scratch, 'profile'),
    '--window-size=' + width + ',' + height, 'about:blank'
  ], { stdio: ['ignore', 'ignore', 'pipe'] });
  let chromeLog = '';
  chrome.stderr.on('data', data => chromeLog += data.toString());
  let port;
  for (let i = 0; i < 100; i++) {
    if (chrome.exitCode !== null) throw new Error('Chromium exited: ' + chromeLog.slice(-1000));
    try { port = Number((await readFile(join(scratch, 'profile', 'DevToolsActivePort'), 'utf8')).split('\n')[0]); break; } catch {}
    await delay(50);
  }
  if (!port) throw new Error('No Chromium debugging port.');
  const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
  const target = pages.find(page => page.type === 'page');
  socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push(message.params.exceptionDetails);
    const waiter = pending.get(message.id);
    if (waiter) {
      clearTimeout(waiter.timer); pending.delete(message.id);
      if (message.error) waiter.reject(new Error(JSON.stringify(message.error))); else waiter.resolve(message.result);
    }
  });
  await command('Page.enable');
  await command('Runtime.enable');
  await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
  await navigate('?record=1&paused=1');
  const chapters = await evaluate('AdvisorAnimation.chapters');
  const duration = await evaluate('AdvisorAnimation.duration');
  const checks = [];
  for (const chapter of chapters) {
    const png = await imageAt(chapter.start + 1.5);
    await writeFile(join(output, 'chapter-' + String(chapter.index + 1).padStart(2, '0') + '.png'), png);
    const check = await evaluateFunction(() => {
      const box = id => document.getElementById(id).getBoundingClientRect();
      const scene = box('scene'), note = box('note'), intro = box('intro'), board = box('board');
      const overflow = [...document.querySelectorAll('#scene *')].filter(element => {
        const r = element.getBoundingClientRect();
        if (!r.width || !r.height) return false;
        return r.right > scene.right + 1 || r.left < scene.left - 1 || r.bottom > note.top - 6;
      }).map(element => ({ tag: element.tagName, text: element.textContent.slice(0, 100) }));
      return {
        stage: AdvisorAnimation.state().stage,
        heading: document.getElementById('heading').textContent,
        overflow,
        introOverlaps: intro.bottom > scene.top,
        viewportFits: Math.abs(board.width - innerWidth) < 1 && Math.abs(board.height - innerHeight) < 1,
        externalResources: performance.getEntriesByType('resource').filter(r => /^https?:/.test(r.name)).map(r => r.name)
      };
    });
    checks.push(check);
  }
  const layoutFailures = checks.filter(check => check.overflow.length || check.introOverlaps || !check.viewportFits || check.externalResources.length);
  if (layoutFailures.length) {
    await writeFile(join(output, 'checks.json'), JSON.stringify({ checks, runtimeErrors }, null, 2));
    throw new Error('Layout validation failed: ' + JSON.stringify(layoutFailures));
  }
  // Check the actual handlers, plus reduced-motion autoplay behavior.
  await navigate('?paused=1');
  const controls = await evaluateFunction(() => {
    const seek = document.getElementById('seek');
    seek.value = 32; seek.dispatchEvent(new Event('input'));
    const scrub = AdvisorAnimation.state().stage === 6 && !AdvisorAnimation.state().playing;
    document.getElementById('play').click();
    const play = AdvisorAnimation.state().playing;
    document.getElementById('play').click();
    const pause = !AdvisorAnimation.state().playing;
    document.getElementById('restart').click();
    const restart = AdvisorAnimation.state().time === 0 && AdvisorAnimation.state().playing;
    AdvisorAnimation.pause();
    return { scrub, play, pause, restart, recordingLink: document.getElementById('record-link').getAttribute('href') === '?record=1' };
  });
  if (Object.values(controls).some(value => !value)) throw new Error('Playback check failed.');
  await command('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
  await navigate('?record=1');
  const reducedMotion = !(await evaluate('AdvisorAnimation.state().playing'));
  if (!reducedMotion) throw new Error('Reduced motion did not pause autoplay.');
  await command('Emulation.setEmulatedMedia', { features: [] });
  await navigate('?record=1&paused=1');
  if (runtimeErrors.length) throw new Error('Browser JavaScript errors.');
  const sourceHash = createHash('sha256').update(await readFile(source)).digest('hex');
  await writeFile(join(output, 'checks.json'), JSON.stringify({ sourceHash, checks, controls, reducedMotion, runtimeErrors, duration, width, height, fps }, null, 2));
  console.log('Verified all ' + chapters.length + ' chapters, layout, playback controls and reduced motion.');
  if (!checkOnly) {
    const mp4 = join(output, 'ai-site-advisor-architecture.mp4');
    ffmpeg = spawn('ffmpeg', [
      '-hide_banner', '-loglevel', 'error', '-y',
      '-f', 'image2pipe', '-vcodec', 'png', '-framerate', String(fps), '-i', '-',
      '-an', '-c:v', 'libx264', '-preset', 'fast', '-crf', '19', '-pix_fmt', 'yuv420p',
      '-movflags', '+faststart', mp4
    ], { stdio: ['pipe', 'ignore', 'pipe'] });
    let ffmpegErrors = '';
    ffmpeg.stderr.on('data', data => ffmpegErrors += data.toString());
    ffmpeg.stdin.on('error', () => {});
    const complete = once(ffmpeg, 'close');
    for (let frame = 0; frame < duration * fps; frame++) {
      const png = await imageAt(frame / fps);
      if (!ffmpeg.stdin.write(png)) await once(ffmpeg.stdin, 'drain');
      if (frame % (fps * 5) === 0) console.log('Rendered ' + Math.floor(frame / fps) + ' / ' + duration + ' seconds');
    }
    ffmpeg.stdin.end();
    const [exitCode] = await complete;
    if (exitCode !== 0) throw new Error('Video encode failed: ' + ffmpegErrors);
    await run('ffmpeg', [
      '-hide_banner', '-loglevel', 'error', '-y', '-i', mp4,
      '-filter_complex', '[0:v]fps=12,scale=960:-1:flags=lanczos,split[a][b];[a]palettegen=stats_mode=diff[p];[b][p]paletteuse=dither=bayer:bayer_scale=5:diff_mode=rectangle',
      '-loop', '0', join(output, 'ai-site-advisor-architecture.gif')
    ]);
    const metadata = JSON.parse(await run('ffprobe', ['-v', 'error', '-show_streams', '-show_format', '-of', 'json', mp4]));
    if (metadata.streams.length !== 1 || metadata.streams[0].codec_type !== 'video' || Math.abs(Number(metadata.format.duration) - duration) > 0.05) throw new Error('Unexpected video streams or duration.');
    await writeFile(join(output, 'video-metadata.json'), JSON.stringify(metadata, null, 2));
    console.log('Created silent MP4 and looping GIF in ' + output);
  }
} finally {
  for (const waiter of pending.values()) clearTimeout(waiter.timer);
  if (socket) socket.close();
  if (ffmpeg && ffmpeg.exitCode === null) ffmpeg.kill('SIGTERM');
  if (chrome && chrome.exitCode === null) {
    chrome.kill('SIGTERM');
    await Promise.race([once(chrome, 'close'), delay(3000)]);
  }
  await rm(scratch, { recursive: true, force: true });
}
