(function (Drupal, once) {
  Drupal.behaviors.aiSiteAdvisor = {
    attach(context) {
      once('advisor-brief', '[data-advisor-brief], [data-advisor-query]', context).forEach((field) => {
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
          const query = button.closest('form').querySelector('[data-advisor-query]');
          if (query) {
            query.value = button.dataset.advisorSearch || '';
          }
          field.dispatchEvent(new Event('input', { bubbles: true }));
          field.focus();
        });
      });
    },
  };
})(Drupal, once);
