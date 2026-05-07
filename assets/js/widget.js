(function () {
  'use strict';

  var settings = window.carbonReproWidget || {};
  var smsNumber = settings.smsNumber || '7137056097';
  var ajaxUrl = settings.ajaxUrl || '';
  var nonce = settings.nonce || '';
  var pendingWidgetSubmission = false;

  document.documentElement.setAttribute('data-widget-hidden', 'true');

  function getElements() {
    return {
      widget: document.getElementById('watchWidget'),
      launcherButton: document.getElementById('watchBtn'),
      launcherLabel: document.getElementById('watchLabel'),
      menu: document.getElementById('watchMenu'),
      form: document.getElementById('watchFormPopup'),
      overlay: document.getElementById('watchOverlay'),
      menuClose: document.getElementById('watchMenuCloseBtn'),
      formBack: document.getElementById('watchFormBackBtn'),
      formClose: document.getElementById('watchFormCloseBtn')
    };
  }

  function getAttributionData() {
    var data = {
      current: {
        utm_source: '', utm_medium: '', utm_campaign: '', utm_term: '', utm_content: '',
        gclid: '', fbclid: '', referrer: document.referrer, landing: window.location.href
      },
      first: JSON.parse(localStorage.getItem('criaw_first_touch') || '{}')
    };

    var urlParams = new URLSearchParams(window.location.search);
    var utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
    
    utms.forEach(function(key) {
      if (urlParams.has(key)) {
        data.current[key] = urlParams.get(key);
      }
    });

    if (!data.first.at) {
      data.first = {
        at: new Date().toISOString(),
        utm_source: data.current.utm_source,
        referrer: document.referrer,
        landing: window.location.href
      };
      localStorage.setItem('criaw_first_touch', JSON.stringify(data.first));
    }

    return data;
  }

  function trackEvent(eventName) {
    if (!ajaxUrl || !nonce || !eventName) {
      return;
    }

    var attr = getAttributionData();
    var payload = new window.FormData();
    payload.append('action', 'criaw_track_event');
    payload.append('nonce', nonce);
    payload.append('event', eventName);
    
    // Last Touch / Current
    Object.keys(attr.current).forEach(function(key) {
      payload.append('attr_' + key, attr.current[key]);
    });

    // First Touch
    Object.keys(attr.first).forEach(function(key) {
      payload.append('first_' + key, attr.first[key]);
    });

    if (typeof window.fetch === 'function') {
      window.fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload
      }).catch(function () {});
      return;
    }

    if (typeof window.jQuery !== 'undefined') {
      var jqData = {
        action: 'criaw_track_event',
        nonce: nonce,
        event: eventName
      };
      Object.keys(attr.current).forEach(function(key) { jqData['attr_' + key] = attr.current[key]; });
      Object.keys(attr.first).forEach(function(key) { jqData['first_' + key] = attr.first[key]; });
      window.jQuery.post(ajaxUrl, jqData);
    }
  }

  function setExpandedState(menu, form) {
    if (menu) {
      menu.setAttribute('aria-hidden', menu.classList.contains('active') ? 'false' : 'true');
    }

    if (form) {
      form.setAttribute('aria-hidden', form.classList.contains('active') ? 'false' : 'true');
    }
  }

  function closeWidget(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    var elements = getElements();
    if (!elements.menu || !elements.form || !elements.overlay) {
      return;
    }

    elements.menu.classList.remove('active');
    elements.form.classList.remove('active');
    elements.overlay.classList.remove('active');
    setExpandedState(elements.menu, elements.form);

    window.setTimeout(function () {
      if (!elements.menu.classList.contains('active') && !elements.form.classList.contains('active')) {
        document.documentElement.setAttribute('data-widget-hidden', 'true');
      }
    }, 320);
  }

  function openMenu() {
    var elements = getElements();
    if (!elements.menu || !elements.overlay || !elements.form) {
      return;
    }

    if (elements.menu.classList.contains('active')) {
      return;
    }

    document.documentElement.removeAttribute('data-widget-hidden');
    elements.menu.classList.add('active');
    elements.form.classList.remove('active');
    elements.overlay.classList.add('active');
    setExpandedState(elements.menu, elements.form);
    trackEvent('widget_open');
  }

  function toggleWidget(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    var elements = getElements();
    if (!elements.menu) {
      return;
    }

    if (elements.menu.classList.contains('active')) {
      closeWidget(event);
      return;
    }

    openMenu();
  }

  function showForm(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    var elements = getElements();
    if (!elements.menu || !elements.form) {
      return;
    }

    document.documentElement.removeAttribute('data-widget-hidden');
    elements.menu.classList.remove('active');
    elements.form.classList.add('active');

    if (elements.overlay) {
      elements.overlay.classList.add('active');
    }

    setExpandedState(elements.menu, elements.form);
    trackEvent('show_form');

    window.setTimeout(function () {
      if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document).trigger('wpformsReady');
      }
    }, 300);
  }

  function hideForm(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    var elements = getElements();
    if (!elements.menu || !elements.form) {
      return;
    }

    elements.form.classList.remove('active');
    elements.menu.classList.add('active');
    setExpandedState(elements.menu, elements.form);
  }

  function callUs(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    trackEvent('call_click');
    window.location.href = 'tel:' + smsNumber;
  }

  function textUs(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    trackEvent('text_click');
    var message = "Hello! I need assistance with your services. (I'm currently on: " + window.location.href + ")";
    var isIos = !!window.navigator.userAgent.match(/iPhone|iPad|iPod/i);
    window.location.href = 'sms:' + smsNumber + (isIos ? '&' : '?') + 'body=' + encodeURIComponent(message);
  }

  function handleActionKeydown(handler) {
    return function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        handler(event);
      }
    };
  }

  function bindAction(action, handler) {
    if (!action) {
      return;
    }

    action.addEventListener('click', handler);
    action.addEventListener('keydown', handleActionKeydown(handler));
  }

  function bindWidgetFormTracking(widget) {
    var formPopup = widget.querySelector('#watchFormPopup');
    if (!formPopup) {
      return;
    }

    function handleWidgetSubmitSuccess() {
      if (pendingWidgetSubmission) {
        trackEvent('form_submit');
        pendingWidgetSubmission = false;
      }

      window.setTimeout(closeWidget, 1500);
    }

    formPopup.addEventListener('submit', function (event) {
      if (event.target && formPopup.contains(event.target)) {
        pendingWidgetSubmission = true;
      }
    }, true);

    document.addEventListener('wpformsAjaxSubmitSuccess', handleWidgetSubmitSuccess);
    document.addEventListener('wpformsSubmitSuccess', handleWidgetSubmitSuccess);

    if (typeof window.jQuery !== 'undefined') {
      window.jQuery(document).on('wpformsAjaxSubmitSuccess wpformsSubmitSuccess', handleWidgetSubmitSuccess);
    }
  }

  // Opens the form and pre-selects a service option by its visible label text.
  // Scans all <select> elements inside the form body for a matching option,
  // then fires both a native and jQuery change event so WPForms conditional
  // logic reveals the corresponding sub-dropdown automatically.
  function openFormWithService(serviceName) {
    showForm();

    if (!serviceName) {
      return;
    }

    window.setTimeout(function () {
      var formBody = document.querySelector('.watch-form-body');
      if (!formBody) {
        return;
      }

      var selects = formBody.querySelectorAll('select');
      var targetSelect = null;
      var targetValue = null;

      for (var i = 0; i < selects.length; i++) {
        var opts = selects[i].options;
        for (var j = 0; j < opts.length; j++) {
          if (opts[j].text.trim() === serviceName) {
            targetSelect = selects[i];
            targetValue = opts[j].value;
            break;
          }
        }
        if (targetSelect) {
          break;
        }
      }

      if (!targetSelect || targetValue === null) {
        return;
      }

      targetSelect.value = targetValue;

      targetSelect.dispatchEvent(new Event('change', { bubbles: true }));

      if (typeof window.jQuery !== 'undefined') {
        window.jQuery(targetSelect).trigger('change');
      }
    }, 500);
  }

  window.toggleWidget = toggleWidget;
  window.showForm = showForm;
  window.hideForm = hideForm;
  window.callUs = callUs;
  window.textUs = textUs;
  window.closeWidget = closeWidget;
  window.openFormWithService = openFormWithService;

  document.addEventListener('DOMContentLoaded', function () {
    var elements = getElements();
    if (!elements.widget) {
      return;
    }

    if (elements.launcherButton) {
      elements.launcherButton.addEventListener('click', toggleWidget);
    }

    if (elements.launcherLabel) {
      elements.launcherLabel.addEventListener('click', toggleWidget);
    }

    if (elements.overlay) {
      elements.overlay.addEventListener('click', closeWidget);
    }

    if (elements.menuClose) {
      elements.menuClose.addEventListener('click', closeWidget);
    }

    if (elements.formBack) {
      elements.formBack.addEventListener('click', hideForm);
    }

    if (elements.formClose) {
      elements.formClose.addEventListener('click', closeWidget);
    }

    bindAction(document.querySelector('[data-watch-action="show-form"]'), showForm);
    bindAction(document.querySelector('[data-watch-action="call"]'), callUs);
    bindAction(document.querySelector('[data-watch-action="text"]'), textUs);

    document.querySelectorAll('.trigger-instant-actions').forEach(function (button) {
      button.addEventListener('click', toggleWidget);
    });

    // Service-specific form triggers.
    // Usage: <button class="trigger-service-form" data-service="Print Media">...</button>
    // The data-service value must exactly match the visible option label in the WPForms
    // Select Service field. On click the form opens and that option is pre-selected,
    // which causes WPForms conditional logic to reveal the matching sub-dropdown.
    document.querySelectorAll('.trigger-service-form').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        openFormWithService(button.getAttribute('data-service') || '');
      });
    });

    bindWidgetFormTracking(elements.widget);

    document.addEventListener('click', function (event) {
      var menuActive = elements.menu && elements.menu.classList.contains('active');
      var formActive = elements.form && elements.form.classList.contains('active');

      if (!menuActive && !formActive) {
        return;
      }

      if (event.target.closest && event.target.closest('.lity')) {
        return;
      }

      if (elements.widget.contains(event.target)) {
        return;
      }

      closeWidget(event);
    }, true);

    document.addEventListener('keydown', function (event) {
      var menuActive = elements.menu && elements.menu.classList.contains('active');
      var formActive = elements.form && elements.form.classList.contains('active');

      if ((menuActive || formActive) && (event.key === 'Escape' || event.keyCode === 27)) {
        closeWidget();
      }
    });

    document.addEventListener('lity:open', function () {
      var menuActive = elements.menu && elements.menu.classList.contains('active');
      var formActive = elements.form && elements.form.classList.contains('active');

      if (menuActive || formActive) {
        closeWidget();
      }
    });
  });

  window.addEventListener('resize', function () {
    var elements = getElements();
    if (!elements.menu || !elements.form) {
      return;
    }

    if (window.innerWidth <= 768 &&
      (elements.menu.classList.contains('active') || elements.form.classList.contains('active'))) {
      closeWidget();
    }
  });
}());
