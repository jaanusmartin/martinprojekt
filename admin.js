(() => {
  'use strict';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const statusLabels = { new: 'Uus', answered: 'Vastatud', closed: 'Suletud' };
  let ticketsByCode = new Map();
  let selectedCode = '';

  async function api(action, payload = {}) {
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Päring ebaõnnestus.');
    return data;
  }

  function showMessage(message, isError = false) {
    const element = document.getElementById('admin-message');
    element.textContent = message;
    element.classList.toggle('error-message', isError);
  }

  const svgNS = 'http://www.w3.org/2000/svg';
  function drawMoodChart(entries) {
    const svg = document.getElementById('admin-mood-chart');
    svg.replaceChildren();
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const add = (tag, attrs) => {
      const element = document.createElementNS(svgNS, tag);
      Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));
      svg.append(element);
      return element;
    };
    [1, 2, 3, 4, 5].forEach((score) => {
      const y = 205 - ((score - 1) * 44);
      add('line', { x1: 40, y1: y, x2: 620, y2: y, class: 'chart-grid-line' });
      const label = add('text', { x: 16, y: y + 4, class: 'chart-axis-label' });
      label.textContent = String(score);
    });
    const startLabel = add('text', { x: 44, y: 230, class: 'chart-axis-label' });
    startLabel.textContent = firstDay.toLocaleDateString('et-EE', { day: 'numeric', month: 'numeric' });
    const endLabel = add('text', { x: 620, y: 230, 'text-anchor': 'end', class: 'chart-axis-label' });
    endLabel.textContent = lastDay.toLocaleDateString('et-EE', { day: 'numeric', month: 'numeric' });
    if (!entries.length) {
      const empty = add('text', { x: 330, y: 120, 'text-anchor': 'middle', class: 'chart-axis-label' });
      empty.textContent = 'Viimase 30 päeva kohta pole check-in’e';
      document.getElementById('mood-chart-caption').textContent = 'Check-in’id on anonüümsed ning vabatahtlikud.';
      return;
    }
    const points = entries.map((item) => ({
      x: 50 + (Math.max(0, Math.min(29, (new Date(`${item.date}T12:00:00`) - firstDay) / 86400000)) / 29) * 560,
      y: 205 - ((item.average - 1) * 44),
      item,
    }));
    add('polyline', { points: points.map((point) => `${point.x},${point.y}`).join(' '), class: 'admin-line' });
    points.forEach(({ x, y }) => add('circle', { cx: x, cy: y, r: 5, class: 'admin-dot' }));
    document.getElementById('mood-chart-caption').textContent =
      `${entries.reduce((sum, item) => sum + item.count, 0)} anonüümset check-in’i ${entries.length} päeval.`;
  }

  function drawCategories(categories) {
    const container = document.getElementById('category-chart');
    container.replaceChildren();
    const entries = Object.entries(categories).sort((a, b) => b[1] - a[1]);
    const total = entries.reduce((sum, [, count]) => sum + count, 0);
    if (!total) {
      const empty = document.createElement('p');
      empty.className = 'category-empty';
      empty.textContent = 'Andmeid pole veel kogunenud.';
      container.append(empty);
      return;
    }
    entries.forEach(([category, count]) => {
      const row = document.createElement('div');
      row.className = 'category-row';
      const label = document.createElement('span');
      label.textContent = category;
      const bar = document.createElement('div');
      bar.className = 'category-bar';
      const fill = document.createElement('span');
      fill.style.width = `${(count / total) * 100}%`;
      bar.append(fill);
      const percent = document.createElement('strong');
      percent.textContent = `${Math.round((count / total) * 100)}%`;
      row.append(label, bar, percent);
      container.append(row);
    });
  }

  function renderAdminConversation(ticket) {
    const conversation = document.getElementById('admin-conversation');
    conversation.replaceChildren();
    ticket.messages.forEach((message) => {
      const article = document.createElement('article');
      article.className = `chat-message ${message.sender === 'admin' ? 'from-admin' : 'from-student'}`;
      const sender = document.createElement('strong');
      sender.textContent = message.sender === 'admin' ? 'Tugispetsialist' : 'Õpilane';
      const text = document.createElement('p');
      text.textContent = message.text;
      const time = document.createElement('time');
      const date = new Date(message.createdAt);
      time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('et-EE');
      article.append(sender, text, time);
      conversation.append(article);
    });
  }

  function openTicket(code) {
    const ticket = ticketsByCode.get(code);
    if (!ticket) return;
    selectedCode = code;
    document.getElementById('modal-ticket-title').textContent = code;
    document.getElementById('ticket-status').value = ticket.status;
    renderAdminConversation(ticket);
    document.getElementById('ticket-modal').hidden = false;
    document.getElementById('admin-reply').focus();
  }

  function renderTickets(tickets) {
    const container = document.getElementById('ticket-list');
    container.replaceChildren();
    ticketsByCode = new Map(tickets.map((ticket) => [ticket.code, ticket]));
    if (!tickets.length) {
      const empty = document.createElement('p');
      empty.textContent = 'Pöördumisi pole veel saabunud.';
      container.append(empty);
      return;
    }
    tickets.forEach((ticket) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-ticket';
      const code = document.createElement('strong');
      code.textContent = ticket.code;
      const status = document.createElement('span');
      status.className = `status-badge status-${ticket.status}`;
      status.textContent = statusLabels[ticket.status] || ticket.status;
      const preview = document.createElement('p');
      preview.className = 'ticket-preview';
      preview.textContent = ticket.messages[ticket.messages.length - 1]?.text || 'Sõnum puudub';
      button.append(code, status, preview);
      button.addEventListener('click', () => openTicket(ticket.code));
      container.append(button);
    });
  }

  async function loadDashboard() {
    showMessage('Laen andmeid…');
    try {
      const data = await api('admin_data');
      drawMoodChart(data.averages);
      drawCategories(data.categories);
      renderTickets(data.tickets);
      showMessage('');
      if (selectedCode && ticketsByCode.has(selectedCode) && !document.getElementById('ticket-modal').hidden) {
        openTicket(selectedCode);
      }
    } catch (error) {
      showMessage(error.message, true);
    }
  }

  document.getElementById('admin-refresh').addEventListener('click', loadDashboard);
  document.getElementById('modal-close').addEventListener('click', () => {
    document.getElementById('ticket-modal').hidden = true;
  });
  document.getElementById('ticket-modal').addEventListener('click', (event) => {
    if (event.target.id === 'ticket-modal') event.currentTarget.hidden = true;
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.getElementById('ticket-modal').hidden = true;
  });
  document.getElementById('admin-reply-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = event.currentTarget.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
      await api('admin_reply', { code: selectedCode, message: document.getElementById('admin-reply').value });
      document.getElementById('admin-reply').value = '';
      await loadDashboard();
    } catch (error) {
      showMessage(error.message, true);
    } finally {
      submit.disabled = false;
    }
  });
  document.getElementById('ticket-status').addEventListener('change', async (event) => {
    try {
      await api('admin_status', { code: selectedCode, status: event.currentTarget.value });
      await loadDashboard();
    } catch (error) {
      showMessage(error.message, true);
      const ticket = ticketsByCode.get(selectedCode);
      if (ticket) event.currentTarget.value = ticket.status;
    }
  });

  loadDashboard();
})();
