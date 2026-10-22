(() => {
  const simulator = document.getElementById('sms-simulator');
  if (!simulator) return;

  const phoneNotifications = document.getElementById('sms-notifications');
  const activityList = document.getElementById('sms-activity-list');
  const activityEmpty = document.getElementById('sms-activity-empty');
  const emptyState = document.getElementById('sms-empty-state');
  const status = document.getElementById('sms-simulator-status');
  const demoButton = document.getElementById('send-demo-sms');
  let newestId = 0;
  let loadingMessages = false;

  const updateClock = () => {
    const now = new Date();
    document.getElementById('device-clock').textContent =
      now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    document.getElementById('device-large-clock').textContent =
      now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    document.getElementById('device-date').textContent =
      now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
  };

  const formatTime = (value) => {
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime())
      ? value
      : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  };

  const addMessage = (message, animate) => {
    const id = Number(message.sms_log_id);
    if (!Number.isSafeInteger(id) || id <= newestId) return;
    newestId = id;
    emptyState?.remove();
    activityEmpty?.remove();

    const notification = document.createElement('article');
    notification.className = 'sms-notification';
    if (!animate) notification.style.animation = 'none';

    const head = document.createElement('div');
    head.className = 'sms-notification-head';
    const icon = document.createElement('span');
    icon.className = 'sms-notification-icon';
    icon.innerHTML = '<i class="bi bi-chat-fill" aria-hidden="true"></i>';
    const appName = document.createElement('span');
    appName.textContent = 'Messages';
    const time = document.createElement('span');
    time.className = 'sms-notification-time';
    time.textContent = formatTime(message.timestamp);
    head.append(icon, appName, time);

    const sender = document.createElement('div');
    sender.className = 'sms-notification-sender';
    sender.textContent = 'Desire’s Tailor Shop';
    const content = document.createElement('div');
    content.className = 'sms-notification-message';
    content.textContent = message.message_content;
    notification.append(head, sender, content);
    phoneNotifications.prepend(notification);

    const activity = document.createElement('article');
    activity.className = 'sms-activity-item';
    if (!animate) activity.style.animation = 'none';
    const activityIcon = document.createElement('div');
    activityIcon.className = 'sms-activity-item-icon';
    activityIcon.innerHTML = '<i class="bi bi-chat-dots" aria-hidden="true"></i>';
    const details = document.createElement('div');
    const title = document.createElement('div');
    title.className = 'fw-semibold small';
    title.textContent = message.trigger_type;
    const body = document.createElement('div');
    body.className = 'sms-activity-item-message';
    body.textContent = message.message_content;
    const meta = document.createElement('div');
    meta.className = 'sms-activity-item-meta';
    meta.textContent = `${message.recipient_number} · ${formatTime(message.timestamp)} · Mocked`;
    details.append(title, body, meta);
    activity.append(activityIcon, details);
    activityList.prepend(activity);

    while (phoneNotifications.children.length > 5) {
      phoneNotifications.lastElementChild.remove();
    }
    while (activityList.children.length > 10) {
      activityList.lastElementChild.remove();
    }
    status.textContent = 'A new mock SMS notification arrived.';
  };

  const loadMessages = async () => {
    if (loadingMessages) return;
    loadingMessages = true;
    try {
      const response = await fetch(
        `/tailorshop/api/mock-sms-feed.php?after_id=${newestId}`,
        { credentials: 'same-origin', cache: 'no-store' }
      );
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Could not load SMS messages.');
      data.messages.forEach((message) => addMessage(message, newestId > 0));
    } catch (error) {
      status.textContent = error.message;
    } finally {
      loadingMessages = false;
    }
  };

  if (demoButton) {
    demoButton.addEventListener('click', async () => {
      demoButton.disabled = true;
      status.textContent = 'Creating a mock SMS notification.';
      try {
        const response = await fetch('/tailorshop/api/mock-sms-demo.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': simulator.dataset.csrfToken },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not create demo SMS.');
        status.textContent = 'Demo SMS created. The notification will appear shortly.';
        await loadMessages();
      } catch (error) {
        status.textContent = error.message;
      } finally {
        demoButton.disabled = false;
      }
    });
  }

  updateClock();
  window.setInterval(updateClock, 15000);
  loadMessages();
  window.setInterval(loadMessages, 2000);
})();
