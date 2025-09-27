(function() {
  const consentKey = 'lb_consent';
  const banner = document.querySelector('.consent-banner');
  const acceptBtn = document.querySelector('.consent-accept');
  if (banner && acceptBtn && navigator.doNotTrack !== '1') {
    const saved = localStorage.getItem(consentKey);
    if (!saved) {
      banner.classList.add('consent-banner-active');
    }
    acceptBtn.addEventListener('click', function() {
      fetch('/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'consent.accept', payload: { version: banner.dataset.version } })
      }).then(function(resp) { return resp.json(); }).then(function(data) {
        if (data.success) {
          localStorage.setItem(consentKey, JSON.stringify({ ts: Date.now(), version: banner.dataset.version }));
          banner.classList.remove('consent-banner-active');
        }
      }).catch(function() { banner.classList.remove('consent-banner-active'); });
    });
  }

  document.querySelectorAll('[data-ai-generate]').forEach(function(button) {
    button.addEventListener('click', function() {
      const targetId = this.dataset.aiTarget;
      const textarea = document.getElementById(targetId);
      const type = this.dataset.aiType;
      if (!textarea) { return; }
      button.disabled = true;
      const params = {
        action: 'ai.generate',
        payload: {
          type: type,
          text: textarea.value,
          category: this.dataset.aiCategory || '',
          tone: this.dataset.aiTone || 'warm',
          audience: this.dataset.aiAudience || 'familien',
          keywords: this.dataset.aiKeywords || ''
        }
      };
      fetch('/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(params)
      }).then(function(resp) { return resp.json(); }).then(function(data) {
        if (data.success && data.text) {
          textarea.value = data.text;
        }
      }).finally(function() {
        button.disabled = false;
      });
    });
  });
})();
