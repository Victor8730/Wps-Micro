import '../css/app.css';

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-validate]').forEach((form) => {
    const fields = [...form.querySelectorAll('input, select, textarea')]
      .filter((field) => field.type !== 'hidden');

    const updateField = (field) => {
      field.setAttribute('aria-invalid', field.checkValidity() ? 'false' : 'true');
    };

    fields.forEach((field) => {
      field.addEventListener('input', () => {
        if (form.dataset.validated === 'true' || field.getAttribute('aria-invalid') === 'true') {
          updateField(field);
        }
      });
    });

    form.addEventListener('submit', (event) => {
      form.dataset.validated = 'true';
      fields.forEach(updateField);

      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        form.querySelector(':invalid')?.focus();
      }
    });
  });
});
