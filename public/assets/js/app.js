(function () {
  'use strict';

  // ---- Confirmation modal --------------------------------------------
  // Any <form data-confirm="message" data-confirm-title="title"> has its
  // submit intercepted, and any <a data-confirm="..."> has its click
  // intercepted; either way the shared #confirm-dialog is shown and the
  // original action (form submit, or navigating to the link's href) only
  // happens once the user clicks Confirm.
  function initConfirmDialogs() {
    var dialog = document.getElementById('confirm-dialog');
    if (!dialog) {
      return;
    }
    var titleEl = document.getElementById('confirm-dialog-title');
    var messageEl = document.getElementById('confirm-dialog-message');
    var confirmBtn = document.getElementById('confirm-dialog-confirm');
    var cancelBtn = document.getElementById('confirm-dialog-cancel');
    var pendingAction = null; // () => void, runs the real action once confirmed

    function open(source, run) {
      pendingAction = run;
      titleEl.textContent = source.getAttribute('data-confirm-title') || 'Please confirm';
      messageEl.textContent = source.getAttribute('data-confirm') || 'Are you sure?';
      confirmBtn.className = source.hasAttribute('data-confirm-danger') ? 'btn-primary btn-danger' : 'btn-primary';
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else if (window.confirm(messageEl.textContent)) {
        run();
        pendingAction = null;
      }
    }

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      // A submit button can override the form's own data-confirm (used when
      // one form has several submit buttons with different consequences,
      // e.g. Verify vs Reject sharing a review form).
      var submitter = event.submitter;
      var source = (submitter && submitter.hasAttribute('data-confirm')) ? submitter : form;
      if (!source.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
        return;
      }
      event.preventDefault();
      open(source, function () {
        if (submitter && submitter.hasAttribute('formaction')) {
          form.action = submitter.getAttribute('formaction');
        }
        form.dataset.confirmed = '1';
        form.submit();
      });
    });

    document.addEventListener('click', function (event) {
      var link = event.target.closest ? event.target.closest('a[data-confirm]') : null;
      if (!link || link.dataset.confirmed === '1') {
        return;
      }
      event.preventDefault();
      open(link, function () {
        link.dataset.confirmed = '1';
        window.location.href = link.href;
      });
    });

    confirmBtn.addEventListener('click', function () {
      dialog.close();
      if (pendingAction) {
        pendingAction();
        pendingAction = null;
      }
    });

    cancelBtn.addEventListener('click', function () {
      dialog.close();
      pendingAction = null;
    });

    dialog.addEventListener('cancel', function () {
      pendingAction = null;
    });
  }

  // ---- Sidebar collapse toggle ----------------------------------------
  function initSidebarToggle() {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.querySelector('.sidebar');
    if (!toggle || !sidebar) {
      return;
    }

    function apply(collapsed) {
      sidebar.classList.toggle('collapsed', collapsed);
      // The pre-paint script in head.php sets this on <html> (before app.js
      // runs) purely to avoid a flash of the expanded sidebar on load — once
      // JS is in control, it must stay in sync or its CSS rule silently
      // overrides a later expand.
      document.documentElement.classList.toggle('sidebar-collapsed-pref', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    var stored;
    try {
      stored = localStorage.getItem('sidebarCollapsed') === '1';
    } catch (e) {
      stored = false;
    }
    apply(stored);

    toggle.addEventListener('click', function () {
      var collapsed = !sidebar.classList.contains('collapsed');
      apply(collapsed);
      try {
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      } catch (e) {}
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initConfirmDialogs();
    initSidebarToggle();
  });
})();
