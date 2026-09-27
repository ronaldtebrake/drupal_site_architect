(function (Drupal, once) {
  Drupal.behaviors.siteArchitect = {
    attach(context) {
      once('architect-handoff', '[data-architect-handoff]', context).forEach((handoff) => {
        const button = handoff.querySelector('[data-architect-copy]');
        const text = handoff.querySelector('[data-architect-copy-text]');
        const status = handoff.querySelector('[data-architect-copy-status]');
        button.addEventListener('click', async () => {
          button.disabled = true;
          try {
            await navigator.clipboard.writeText(text.value);
            status.textContent = Drupal.t('Plan copied. Paste it into your agent.');
          }
          catch (error) {
            handoff.querySelector('[data-architect-copy-preview]').open = true;
            text.focus();
            text.select();
            status.textContent = Drupal.t('Automatic copying is unavailable. Copy the selected handoff text manually.');
          }
          finally {
            button.disabled = false;
          }
        });
      });
      once('architect-brief', '[data-architect-brief]', context).forEach((field) => {
        field.addEventListener('input', () => {
          const result = field.closest('form').querySelector('.sa-result');
          if (result) {
            result.hidden = true;
          }
        });
      });
      once('architect-example', '[data-architect-example]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const field = button.closest('form').querySelector('[data-architect-brief]');
          field.value = button.dataset.architectExample;
          field.dispatchEvent(new Event('input', { bubbles: true }));
          field.focus();
        });
      });
    },
  };
})(Drupal, once);
