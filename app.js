(() => {
  'use strict';

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const moodLabels = ['', 'Väga raske', 'Pigem raske', 'Enam-vähem', 'Pigem hästi', 'Hästi'];
  const moodFaces = ['', '😣', '🙁', '😐', '🙂', '😊'];
  const categoryLabels = {
    new: 'Uus',
    answered: 'Vastatud',
    closed: 'Suletud',
  };

  async function api(action, payload = {}) {
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Päring ebaõnnestus. Proovi uuesti.');
    return data;
  }

  function showMessage(element, message, isError = false) {
    if (!element) return;
    element.textContent = message;
    element.classList.toggle('error-message', isError);
  }

  function navigate(panelName) {
    document.querySelectorAll('.panel').forEach((panel) => {
      panel.classList.toggle('active', panel.id === `panel-${panelName}`);
    });
    document.querySelectorAll('.nav-link').forEach((button) => {
      button.classList.toggle('active', button.dataset.panel === panelName);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.querySelectorAll('[data-panel]').forEach((button) => {
    button.addEventListener('click', () => navigate(button.dataset.panel));
  });
  document.querySelectorAll('[data-go]').forEach((button) => {
    button.addEventListener('click', () => navigate(button.dataset.go));
  });

  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    try {
      if (localStorage.getItem('mk-theme') === 'dark') document.documentElement.dataset.theme = 'dark';
    } catch {
      // Theme persistence is optional; the page remains usable without local storage.
    }
    themeToggle.addEventListener('click', () => {
      const isDark = document.documentElement.dataset.theme !== 'dark';
      document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
      try {
        localStorage.setItem('mk-theme', isDark ? 'dark' : 'light');
      } catch {
        // Keep the selected theme for this page view even if it cannot be persisted.
      }
    });
  }

  const HISTORY_KEY = 'meelekompass-checkins-v1';
  function readHistory() {
    try {
      const items = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
      return Array.isArray(items) ? items.filter((item) =>
        item && /^\d{4}-\d{2}-\d{2}$/.test(item.date)
        && Number.isInteger(item.score) && item.score >= 1 && item.score <= 5
        && typeof item.category === 'string'
      ) : [];
    } catch {
      return [];
    }
  }

  function renderMoodHistory() {
    const svg = document.getElementById('mood-chart');
    const list = document.getElementById('history-list');
    if (!svg || !list) return;
    svg.replaceChildren();
    list.replaceChildren();
    const now = new Date();
    const cutoff = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 13);
    const cutoffDate = `${cutoff.getFullYear()}-${String(cutoff.getMonth() + 1).padStart(2, '0')}-${String(cutoff.getDate()).padStart(2, '0')}`;
    const items = readHistory()
      .filter((item) => item.date >= cutoffDate)
      .sort((a, b) => a.date.localeCompare(b.date));
    const ns = 'http://www.w3.org/2000/svg';
    const add = (tag, attrs) => {
      const node = document.createElementNS(ns, tag);
      Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
      svg.append(node);
      return node;
    };

    [1, 2, 3, 4, 5].forEach((score) => {
      const y = 190 - ((score - 1) * 38);
      add('line', { x1: 44, y1: y, x2: 620, y2: y, class: 'chart-grid-line' });
      const label = add('text', { x: 18, y: y + 4, class: 'chart-axis-label' });
      label.textContent = String(score);
    });
    const startLabel = add('text', { x: 48, y: 214, class: 'chart-axis-label' });
    startLabel.textContent = cutoff.toLocaleDateString('et-EE', { day: 'numeric', month: 'numeric' });
    const endLabel = add('text', { x: 615, y: 214, 'text-anchor': 'end', class: 'chart-axis-label' });
    endLabel.textContent = now.toLocaleDateString('et-EE', { day: 'numeric', month: 'numeric' });
    if (items.length) {
      const points = items.map((item) => {
        const itemDate = new Date(`${item.date}T12:00:00`);
        const offset = Math.max(0, Math.min(13, (itemDate - cutoff) / 86400000));
        const x = 54 + (offset / 13) * 560;
        const y = 190 - ((item.score - 1) * 38);
        return { x, y, item };
      });
      add('polyline', {
        points: points.map((point) => `${point.x},${point.y}`).join(' '),
        class: 'mood-line',
      });
      points.forEach(({ x, y, item }) => {
        add('circle', { cx: x, cy: y, r: 5, class: 'mood-dot' });
        const label = add('text', { x, y: y - 12, 'text-anchor': 'middle', class: 'mood-chart-face' });
        label.textContent = moodFaces[item.score];
      });
      [...items].reverse().forEach((item) => {
        const entry = document.createElement('div');
        entry.className = 'history-entry';
        const day = document.createElement('strong');
        day.textContent = new Date(`${item.date}T12:00:00`).toLocaleDateString('et-EE', { day: 'numeric', month: 'short' });
        const mood = document.createElement('span');
        mood.textContent = `${moodFaces[item.score]} ${moodLabels[item.score]}`;
        const category = document.createElement('small');
        category.textContent = item.category;
        entry.append(day, mood, category);
        list.append(entry);
      });
    } else {
      const empty = add('text', { x: 320, y: 112, 'text-anchor': 'middle', class: 'chart-empty' });
      empty.textContent = 'Sinu check-in’id ilmuvad siia';
    }
  }
  renderMoodHistory();

  const checkinForm = document.getElementById('checkin-form');
  checkinForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const score = Number(new FormData(checkinForm).get('mood'));
    const category = document.getElementById('stress-category').value;
    const message = document.getElementById('checkin-message');
    const today = new Date();
    const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const history = readHistory().filter((item) => item.date !== date);
    history.push({ date, score, category });
    try {
      localStorage.setItem(HISTORY_KEY, JSON.stringify(history.slice(-90)));
      renderMoodHistory();
    } catch {
      showMessage(message, 'Selle brauseri salvestusruum pole praegu saadaval. Proovi hiljem uuesti.', true);
      return;
    }
    try {
      await api('checkin', { score, category });
      showMessage(message, 'Aitäh, et märkasid oma enesetunnet. Sinu anonüümne check-in on saadetud.');
    } catch (error) {
      showMessage(message, `Sinu ajalugu salvestus sellesse brauserisse, kuid anonüümset koondkirjet ei saadetud: ${error.message}`, true);
    }
  });

  const toolTabs = document.querySelectorAll('.tool-tab');
  toolTabs.forEach((tab) => tab.addEventListener('click', () => {
    toolTabs.forEach((item) => {
      const selected = item === tab;
      item.classList.toggle('active', selected);
      item.setAttribute('aria-selected', String(selected));
    });
    document.querySelectorAll('.tool-panel').forEach((panel) => {
      const selected = panel.id === `tool-${tab.dataset.tool}`;
      panel.classList.toggle('active', selected);
      panel.hidden = !selected;
    });
  }));

  const breathPatterns = {
    '478': [
      { cue: 'Hinga sisse', seconds: 4, className: 'inhale' },
      { cue: 'Hoia', seconds: 7, className: 'hold' },
      { cue: 'Hinga välja', seconds: 8, className: 'exhale' },
    ],
    box: [
      { cue: 'Hinga sisse', seconds: 4, className: 'inhale' },
      { cue: 'Hoia', seconds: 4, className: 'hold' },
      { cue: 'Hinga välja', seconds: 4, className: 'exhale' },
      { cue: 'Hoia', seconds: 4, className: 'hold' },
    ],
  };
  let breathInterval = null;
  let breathPhaseIndex = 0;
  let breathSecondsLeft = 0;
  let breathRunning = false;
  const breathCue = document.getElementById('breath-cue');
  const breathTimer = document.getElementById('breath-timer');
  const breathCircle = document.getElementById('breath-circle');
  const breathStart = document.getElementById('breath-start');
  const breathStop = document.getElementById('breath-stop');
  const breathSelect = document.getElementById('breath-pattern');

  function setBreathPhase() {
    const phase = breathPatterns[breathSelect.value][breathPhaseIndex];
    breathSecondsLeft = phase.seconds;
    breathCue.textContent = phase.cue;
    breathTimer.textContent = `${breathSecondsLeft} sekundit`;
    breathCircle.className = `breath-circle ${phase.className}`;
  }
  function stopBreathing() {
    clearInterval(breathInterval);
    breathInterval = null;
    breathRunning = false;
    breathCircle.className = 'breath-circle';
    breathCue.textContent = 'Valmis?';
    breathTimer.textContent = 'Võta hetk ja hinga omas tempos.';
    breathStart.disabled = false;
    breathStop.disabled = true;
  }
  breathStart?.addEventListener('click', () => {
    if (breathRunning) return;
    breathRunning = true;
    breathStart.disabled = true;
    breathStop.disabled = false;
    breathPhaseIndex = 0;
    setBreathPhase();
    breathInterval = window.setInterval(() => {
      breathSecondsLeft -= 1;
      if (breathSecondsLeft <= 0) {
        breathPhaseIndex = (breathPhaseIndex + 1) % breathPatterns[breathSelect.value].length;
        setBreathPhase();
      } else {
        breathTimer.textContent = `${breathSecondsLeft} sekundit`;
      }
    }, 1000);
  });
  breathStop?.addEventListener('click', stopBreathing);
  breathSelect?.addEventListener('change', () => {
    if (breathRunning) {
      breathPhaseIndex = 0;
      setBreathPhase();
    }
  });

  const groundingSteps = [
    { count: 5, sense: 'näed' },
    { count: 4, sense: 'tunned oma kehal' },
    { count: 3, sense: 'kuuled' },
    { count: 2, sense: 'tunned lõhna' },
    { count: 1, sense: 'maitsed või mille eest oled tänulik' },
  ];
  let groundingIndex = 0;
  let groundingComplete = false;
  const groundingAnswer = document.getElementById('grounding-answer');
  function renderGroundingStep() {
    const step = groundingSteps[groundingIndex];
    document.getElementById('grounding-count').textContent = `Samm ${groundingIndex + 1} / ${groundingSteps.length}`;
    document.getElementById('grounding-progress').style.width = `${((groundingIndex + 1) / groundingSteps.length) * 100}%`;
    const label = document.querySelector('#grounding-step label');
    label.textContent = `Nimeta ${step.count} asja, mida sa ${step.sense}.`;
    groundingAnswer.value = '';
    document.getElementById('grounding-message').textContent = '';
    document.getElementById('grounding-next').textContent = groundingIndex === groundingSteps.length - 1 ? 'Lõpeta harjutus' : 'Järgmine samm';
  }
  document.getElementById('grounding-next')?.addEventListener('click', () => {
    if (groundingComplete) {
      groundingComplete = false;
      groundingIndex = 0;
      renderGroundingStep();
      return;
    }
    if (!groundingAnswer.value.trim()) {
      showMessage(document.getElementById('grounding-message'), 'Pane vähemalt üks asi tähele või ütle see mõttes.', true);
      return;
    }
    if (groundingIndex < groundingSteps.length - 1) {
      groundingIndex += 1;
      renderGroundingStep();
    } else {
      showMessage(document.getElementById('grounding-message'), 'Hästi tehtud. Sa oled siin ja praegu — võta hetk, et märgata oma hingamist.');
      groundingComplete = true;
      document.getElementById('grounding-count').textContent = 'Harjutus tehtud';
      document.getElementById('grounding-progress').style.width = '100%';
      document.getElementById('grounding-next').textContent = 'Alusta uuesti';
    }
  });
  document.getElementById('grounding-reset')?.addEventListener('click', () => {
    groundingComplete = false;
    groundingIndex = 0;
    renderGroundingStep();
  });

  document.getElementById('crush-worry')?.addEventListener('click', () => {
    const textarea = document.getElementById('worry-text');
    const result = document.getElementById('crush-result');
    if (!textarea.value.trim()) {
      result.textContent = 'Kirjuta kõigepealt üks mõte, mille tahad korraks endast välja panna.';
      return;
    }
    const crushButton = document.getElementById('crush-worry');
    crushButton.disabled = true;
    textarea.classList.add('worry-crushing');
    window.setTimeout(() => {
      textarea.value = '';
      textarea.classList.remove('worry-crushing');
      result.textContent = '✦ See mõte on nüüd kirja pandud. Sa ei pea sellega just praegu midagi tegema. ✦';
      result.classList.remove('crushed');
      void result.offsetWidth;
      result.classList.add('crushed');
      crushButton.disabled = false;
    }, 500);
  });
  document.getElementById('clear-worry')?.addEventListener('click', () => {
    document.getElementById('worry-text').value = '';
    document.getElementById('crush-result').textContent = '';
  });

  const createTicketForm = document.getElementById('ticket-create-form');
  createTicketForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('ticket-create-message');
    const submit = createTicketForm.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      const result = await api('create_ticket', { message: document.getElementById('ticket-message').value });
      document.getElementById('ticket-code').textContent = result.code;
      document.getElementById('ticket-code-card').hidden = false;
      document.getElementById('ticket-code-input').value = result.code;
      createTicketForm.reset();
      showMessage(message, 'Sõnum saadeti turvaliselt. Hoia allolev jälgimiskood alles.');
    } catch (error) {
      showMessage(message, error.message, true);
    } finally {
      submit.disabled = false;
    }
  });

  let activeTicketCode = '';
  async function loadTicket(code) {
    const ticket = await api('get_ticket', { code });
    activeTicketCode = ticket.ticket.code;
    document.getElementById('conversation').hidden = false;
    showMessage(document.getElementById('ticket-reply-message'), '');
    document.getElementById('conversation-status').textContent = `Staatus: ${categoryLabels[ticket.ticket.status] || ticket.ticket.status}`;
    const messages = document.getElementById('conversation-messages');
    messages.replaceChildren();
    ticket.ticket.messages.forEach((item) => {
      const article = document.createElement('article');
      article.className = `chat-message ${item.sender === 'admin' ? 'from-admin' : 'from-student'}`;
      const sender = document.createElement('strong');
      sender.textContent = item.sender === 'admin' ? 'Tugispetsialist' : 'Sina';
      const text = document.createElement('p');
      text.textContent = item.text;
      const time = document.createElement('time');
      const parsed = new Date(item.createdAt);
      time.textContent = Number.isNaN(parsed.getTime()) ? '' : parsed.toLocaleString('et-EE');
      article.append(sender, text, time);
      messages.append(article);
    });
    const closed = ticket.ticket.status === 'closed';
    document.getElementById('ticket-reply').disabled = closed;
    document.querySelector('#ticket-reply-form button').disabled = closed;
    if (closed) showMessage(document.getElementById('ticket-reply-message'), 'See pöördumine on suletud ja uusi sõnumeid saata ei saa.');
  }

  document.getElementById('ticket-load-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const code = document.getElementById('ticket-code-input').value.trim().toUpperCase();
    try {
      await loadTicket(code);
      showMessage(document.getElementById('ticket-load-message'), 'Vestlus on avatud.');
    } catch (error) {
      document.getElementById('conversation').hidden = true;
      showMessage(document.getElementById('ticket-load-message'), error.message, true);
    }
  });
  document.getElementById('refresh-ticket')?.addEventListener('click', async () => {
    try {
      await loadTicket(activeTicketCode);
      showMessage(document.getElementById('ticket-reply-message'), 'Vestlus on värskendatud.');
    } catch (error) {
      showMessage(document.getElementById('ticket-reply-message'), error.message, true);
    }
  });
  document.getElementById('ticket-reply-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = document.getElementById('ticket-reply-message');
    try {
      await api('reply_ticket', { code: activeTicketCode, message: document.getElementById('ticket-reply').value });
      document.getElementById('ticket-reply').value = '';
      await loadTicket(activeTicketCode);
      showMessage(message, 'Sinu sõnum on vestluses.');
    } catch (error) {
      showMessage(message, error.message, true);
    }
  });
})();
