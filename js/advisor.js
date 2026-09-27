(function (Drupal, once) {
  Drupal.behaviors.aiSiteAdvisor = {
    attach(context) {
      once('advisor-handoff', '[data-advisor-handoff]', context).forEach((handoff) => {
        const button = handoff.querySelector('[data-advisor-copy]');
        const text = handoff.querySelector('[data-advisor-copy-text]');
        const status = handoff.querySelector('[data-advisor-copy-status]');
        button.addEventListener('click', async () => {
          button.disabled = true;
          try {
            await navigator.clipboard.writeText(text.value);
            status.textContent = Drupal.t('Plan copied. Paste it into your agent.');
          }
          catch (error) {
            handoff.querySelector('[data-advisor-copy-preview]').open = true;
            text.focus();
            text.select();
            status.textContent = Drupal.t('Automatic copying is unavailable. Copy the selected handoff text manually.');
          }
          finally {
            button.disabled = false;
          }
        });
      });
      once('advisor-brief', '[data-advisor-brief]', context).forEach((field) => {
        field.addEventListener('input', () => {
          const result = field.closest('form').querySelector('.sa-result');
          if (result) {
            result.hidden = true;
          }
        });
      });
      once('advisor-example', '[data-advisor-example]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const field = button.closest('form').querySelector('[data-advisor-brief]');
          field.value = button.dataset.advisorExample;
          field.dispatchEvent(new Event('input', { bubbles: true }));
          field.focus();
        });
      });
    },
  };
})(Drupal, once);
