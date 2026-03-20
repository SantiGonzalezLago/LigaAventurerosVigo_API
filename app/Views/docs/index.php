<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>API Liga de Aventureros Vigo</title>
  <style>
    :root {
      --bg: #f7f4ec;
      --surface: #fffdf6;
      --text: #232323;
      --muted: #676056;
      --line: #d4ccb8;
      --primary: #1b7f5f;
      --primary-2: #0f5f47;
      --accent: #d9a441;
      --danger: #ac3a2f;
      --mono: "JetBrains Mono", "Fira Code", Consolas, monospace;
      --title: "DM Serif Display", "Georgia", serif;
      --sans: "Source Sans 3", "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: var(--sans);
      color: var(--text);
      background:
        radial-gradient(1000px 420px at 15% -10%, #f0e3c2 0%, transparent 55%),
        radial-gradient(1100px 480px at 110% 10%, #d7eadf 0%, transparent 50%),
        var(--bg);
    }

    .wrap {
      width: min(1100px, calc(100% - 2rem));
      margin: 2rem auto 3rem;
    }

    .hero {
      padding: 1.5rem 1.25rem;
      border: 1px solid var(--line);
      border-radius: 16px;
      background: linear-gradient(120deg, #fff8e8 0%, #f5fff9 100%);
      box-shadow: 0 8px 20px rgba(48, 40, 28, 0.06);
    }

    h1 {
      font-family: var(--title);
      margin: 0;
      line-height: 1.1;
      font-size: clamp(1.8rem, 2.5vw + 1rem, 3rem);
      letter-spacing: 0.2px;
    }

    .lead {
      margin: 0.75rem 0 0;
      color: var(--muted);
      font-size: 1.02rem;
    }

    .base-url {
      margin-top: 1rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: #ffffffd1;
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 0.45rem 0.85rem;
      font-family: var(--mono);
      font-size: 0.86rem;
    }

    .grid {
      margin-top: 1.2rem;
      display: grid;
      gap: 1rem;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(36, 30, 20, 0.04);
      animation: appear 260ms ease-out;
    }

    .card-head {
      padding: 1rem 1rem 0.7rem;
      border-bottom: 1px dashed var(--line);
      background: linear-gradient(180deg, #fff9ec 0%, #fffdf6 100%);
      display: flex;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 0.55rem;
    }

    .method {
      font-family: var(--mono);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.8px;
      padding: 0.3rem 0.48rem;
      border-radius: 6px;
      color: #fff;
      background: var(--primary);
    }

    .method.get { background: #2266d3; }

    .path {
      font-family: var(--mono);
      font-size: 0.92rem;
      color: var(--text);
      word-break: break-all;
    }

    .name {
      width: 100%;
      margin: 0.15rem 0 0;
      font-size: 1.16rem;
      font-family: var(--title);
    }

    .card-body {
      padding: 0.9rem 1rem 1rem;
    }

    .desc {
      margin: 0 0 0.9rem;
      color: var(--muted);
    }

    .fields {
      display: grid;
      gap: 0.7rem;
      margin-bottom: 0.9rem;
    }

    .field label {
      display: block;
      font-size: 0.86rem;
      font-weight: 600;
      margin-bottom: 0.35rem;
    }

    .meta {
      color: var(--muted);
      font-weight: 400;
      font-size: 0.8rem;
      margin-left: 0.3rem;
    }

    input[type="text"] {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 0.62rem 0.7rem;
      font-size: 0.94rem;
      background: #fff;
      font-family: var(--mono);
    }

    .actions {
      display: flex;
      gap: 0.65rem;
      flex-wrap: wrap;
      margin-bottom: 0.8rem;
    }

    button {
      border: 0;
      border-radius: 10px;
      padding: 0.62rem 0.8rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform .12s ease, background-color .2s ease;
      font-family: var(--sans);
    }

    .btn-run {
      background: var(--primary);
      color: #fff;
    }

    .btn-run:hover { background: var(--primary-2); }

    .btn-copy {
      background: #efe8d4;
      color: #3a362d;
    }

    button:hover { transform: translateY(-1px); }

    .response {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      overflow: hidden;
    }

    .response-head {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      font-family: var(--mono);
      font-size: 0.82rem;
      padding: 0.45rem 0.6rem;
      border-bottom: 1px solid var(--line);
      color: var(--muted);
      background: #faf7ef;
    }

    .badge {
      border-radius: 999px;
      padding: 0.1rem 0.5rem;
      font-weight: 700;
      color: #fff;
      background: #7a7a7a;
    }

    .badge.ok { background: #21875f; }
    .badge.error { background: var(--danger); }

    pre {
      margin: 0;
      padding: 0.75rem;
      white-space: pre-wrap;
      word-break: break-word;
      max-height: 300px;
      overflow: auto;
      font-family: var(--mono);
      font-size: 0.82rem;
      line-height: 1.42;
    }

    .hint {
      font-size: 0.82rem;
      color: var(--muted);
      margin-top: 0.5rem;
    }

    @keyframes appear {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 700px) {
      .wrap {
        width: calc(100% - 1rem);
        margin-top: 1rem;
      }

      .card-head,
      .card-body {
        padding-left: 0.8rem;
        padding-right: 0.8rem;
      }
    }
  </style>
</head>
<body>
  <main class="wrap">
    <section class="hero">
      <h1>Documentación API v1</h1>
      <div class="base-url">
        <strong>Base URL:</strong>
        <span id="base-url"></span>
      </div>
    </section>

    <section class="grid" id="endpoint-grid"></section>
  </main>

  <script>
    const BASE_PATH = <?= json_encode($basePath ?? '/v1', JSON_UNESCAPED_SLASHES) ?>;
    const ENDPOINTS = <?= json_encode($endpoints ?? [], JSON_UNESCAPED_SLASHES) ?>;

    const endpointGrid = document.getElementById('endpoint-grid');
    const baseUrl = window.location.origin + BASE_PATH;
    document.getElementById('base-url').textContent = baseUrl;

    function statusClass(status) {
      return status >= 200 && status < 300 ? 'ok' : 'error';
    }

    function buildCurl(endpoint, formData) {
      const url = baseUrl + endpoint.path;
      if (endpoint.method === 'GET') {
        return `curl -X GET "${url}"`;
      }

      const params = new URLSearchParams();
      Object.entries(formData).forEach(([k, v]) => params.append(k, v));

      const fields = Object.entries(formData)
        .map(([k, v]) => `-d "${k}=${String(v).replaceAll('"', '\\"')}"`)
        .join(' ');

      return `curl -X POST "${url}" ${fields}`;
    }

    async function executeEndpoint(endpoint, form, outputPre, statusBadge, copyBtn) {
      const url = baseUrl + endpoint.path;
      const payload = {};

      endpoint.request.forEach((field) => {
        const input = form.querySelector(`[name="${field.name}"]`);
        payload[field.name] = input ? input.value : '';
      });

      let response;
      let text;

      try {
        const options = { method: endpoint.method };

        if (endpoint.method !== 'GET') {
          options.headers = { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' };
          options.body = new URLSearchParams(payload).toString();
        }

        response = await fetch(url, options);
        text = await response.text();

        let data;
        try {
          data = JSON.parse(text);
        } catch (_e) {
          data = { raw: text };
        }

        statusBadge.textContent = String(response.status);
        statusBadge.className = `badge ${statusClass(response.status)}`;
        outputPre.textContent = JSON.stringify(data, null, 2);
      } catch (error) {
        statusBadge.textContent = 'ERR';
        statusBadge.className = 'badge error';
        outputPre.textContent = JSON.stringify({
          error: 'No se pudo ejecutar la petición',
          detail: String(error)
        }, null, 2);
      }

      copyBtn.dataset.curl = buildCurl(endpoint, payload);
    }

    function endpointCard(endpoint) {
      const card = document.createElement('article');
      card.className = 'card';

      const fieldsHtml = endpoint.request.length
        ? endpoint.request.map((field) => `
          <div class="field">
            <label>
              ${field.name}
              <span class="meta">(${field.type}${field.required ? ', obligatorio' : ''})</span>
            </label>
            <input type="text" name="${field.name}" value="${field.example || ''}" />
          </div>
        `).join('')
        : '<p class="hint">Este endpoint no necesita parámetros.</p>';

      card.innerHTML = `
        <header class="card-head">
          <span class="method ${endpoint.method.toLowerCase()}">${endpoint.method}</span>
          <span class="path">${endpoint.path}</span>
          <h2 class="name">${endpoint.name}</h2>
        </header>
        <div class="card-body">
          <p class="desc">${endpoint.description}</p>
          <form>
            <div class="fields">${fieldsHtml}</div>
            <div class="actions">
              <button type="submit" class="btn-run">Probar Endpoint</button>
              <button type="button" class="btn-copy">Copiar cURL</button>
            </div>
          </form>
          <div class="response">
            <div class="response-head">
              <span>HTTP</span>
              <span class="badge">-</span>
            </div>
            <pre>{}</pre>
          </div>
        </div>
      `;

      const form = card.querySelector('form');
      const outputPre = card.querySelector('pre');
      const statusBadge = card.querySelector('.badge');
      const copyBtn = card.querySelector('.btn-copy');

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await executeEndpoint(endpoint, form, outputPre, statusBadge, copyBtn);
      });

      copyBtn.addEventListener('click', async () => {
        const curl = copyBtn.dataset.curl || buildCurl(endpoint, {});
        try {
          await navigator.clipboard.writeText(curl);
          copyBtn.textContent = 'cURL Copiado';
          setTimeout(() => { copyBtn.textContent = 'Copiar cURL'; }, 1200);
        } catch (_e) {
          copyBtn.textContent = 'No se pudo copiar';
          setTimeout(() => { copyBtn.textContent = 'Copiar cURL'; }, 1200);
        }
      });

      return card;
    }

    ENDPOINTS.forEach((endpoint) => endpointGrid.appendChild(endpointCard(endpoint)));
  </script>
</body>
</html>
