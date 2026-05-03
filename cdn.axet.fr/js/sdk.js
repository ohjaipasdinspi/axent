/**
 * Axent SDK - sdk.js
 * Widget de consentement aux cookies
 * Version : 1.0.0
 *
 * Usage :
 * <script>
 *   window.axentSettings = {
 *     clientId: "votre-client-id",
 *     cookiesVersion: "votre-version-id",
 *     lang: "fr",           // optionnel (fr|en|es|de)
 *     position: "popup",    // optionnel (popup|bottom-bar|bottom-right)
 *     googleConsentMode: {  // optionnel
 *       default: {
 *         analytics_storage: "denied",
 *         ad_storage: "denied"
 *       }
 *     }
 *   };
 * </script>
 * <script async src="//cdn.axet.fr/sdk.js"></script>
 */

(function (window, document) {
  'use strict';

  // ─── Configuration ───────────────────────────────────────────
  var settings  = window.axentSettings || {};
  var clientId  = settings.clientId;
  var versionId = settings.cookiesVersion;
  var lang      = settings.lang || document.documentElement.lang || 'fr';
  var position  = settings.position || 'popup';
  var apiBase   = 'https://api.axet.fr';
  var cdnBase   = 'https://cdn.axet.fr';
  var COOKIE_NAME = 'axent_consent';
  var COOKIE_DAYS = 365;

  // ─── Textes multilingues ─────────────────────────────────────
  var i18n = {
    fr: {
      title:       'On a des cookies',
      desc:        'Pas les délicieux au chocolat, malheureusement. Mais on vous explique.',
      accept:      'Tout accepter',
      refuse:      'Tout refuser',
      customize:   'Personnaliser',
      save:        'Enregistrer',
      essential:   'Essentiels',
      essDesc:     'Ces cookies font tourner la boutique. Impossible de les désactiver.',
      analytics:   'Statistiques',
      anaDesc:     'Pour savoir si notre site est beau ou nul.',
      marketing:   'Marketing',
      marDesc:     'Pour vous montrer des pubs. Vous avez le droit de dire non.',
      poweredBy:   'Géré par Axent',
    },
    en: {
      title:       'We have cookies',
      desc:        'Not the chocolate ones, sadly. But here\'s the deal.',
      accept:      'Accept all',
      refuse:      'Refuse all',
      customize:   'Customize',
      save:        'Save',
      essential:   'Essential',
      essDesc:     'These cookies keep the lights on. Non-negotiable.',
      analytics:   'Analytics',
      anaDesc:     'To know if our site is good or not.',
      marketing:   'Marketing',
      marDesc:     'To show you ads. You can say no.',
      poweredBy:   'Managed by Axent',
    },
    es: {
      title:       'Tenemos cookies',
      desc:        'No las de chocolate, por desgracia. Pero te explicamos.',
      accept:      'Aceptar todo',
      refuse:      'Rechazar todo',
      customize:   'Personalizar',
      save:        'Guardar',
      essential:   'Esenciales',
      essDesc:     'Estas cookies son indispensables.',
      analytics:   'Estadísticas',
      anaDesc:     'Para saber si nuestro sitio es bueno.',
      marketing:   'Marketing',
      marDesc:     'Para mostrarte anuncios.',
      poweredBy:   'Gestionado por Axent',
    },
    de: {
      title:       'Wir haben Cookies',
      desc:        'Nicht die leckeren mit Schokolade. Aber hier ist die Erklärung.',
      accept:      'Alle akzeptieren',
      refuse:      'Alle ablehnen',
      customize:   'Anpassen',
      save:        'Speichern',
      essential:   'Wesentliche',
      essDesc:     'Diese Cookies sind unverzichtbar.',
      analytics:   'Statistiken',
      anaDesc:     'Um zu wissen, ob unsere Website gut ist.',
      marketing:   'Marketing',
      marDesc:     'Um Ihnen Werbung zu zeigen.',
      poweredBy:   'Verwaltet von Axent',
    }
  };
  var t = i18n[lang] || i18n['fr'];

  // ─── Utilitaires cookies ──────────────────────────────────────
  function setCookie(name, value, days) {
    var expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value)
      + '; expires=' + expires + '; path=/; SameSite=Lax'
      + (location.protocol === 'https:' ? '; Secure' : '');
  }

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  function getConsent() {
    try { return JSON.parse(getCookie(COOKIE_NAME) || 'null'); }
    catch (e) { return null; }
  }

  // ─── Google Consent Mode ──────────────────────────────────────
  function applyGCM(choices) {
    if (typeof window.gtag !== 'function') return;
    var gcm = settings.googleConsentMode;
    if (!gcm) return;

    window.gtag('consent', 'update', {
      analytics_storage: choices.analytics ? 'granted' : 'denied',
      ad_storage:        choices.marketing  ? 'granted' : 'denied',
      ad_user_data:      choices.marketing  ? 'granted' : 'denied',
      ad_personalization:choices.marketing  ? 'granted' : 'denied',
    });
  }

  function initGCM() {
    var gcm = settings.googleConsentMode;
    if (!gcm || typeof window.gtag !== 'function') return;
    window.gtag('consent', 'default', gcm.default || {
      analytics_storage: 'denied',
      ad_storage:        'denied',
      ad_user_data:      'denied',
      ad_personalization:'denied',
      wait_for_update:   500,
    });
  }

  // ─── Envoi du consentement à l'API ───────────────────────────
  function sendConsent(choices, choiceType) {
    if (!clientId || !versionId) return;
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', apiBase + '/consent', true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.send(JSON.stringify({
        clientId:     clientId,
        versionId:    versionId,
        choice:       choiceType,
        categories:   choices,
        lang:         lang,
        url:          location.href,
      }));
    } catch (e) {}
  }

  // ─── Injection CSS ────────────────────────────────────────────
  function injectStyles() {
    var style = document.createElement('style');
    style.id  = 'axent-styles';
    style.textContent = [
      ':root{--axent-primary:#FF6B35;--axent-secondary:#4ECDC4;--axent-dark:#2D3047;--axent-light:#F7F7FF;--axent-radius:20px}',
      '#axent-overlay{position:fixed;inset:0;background:rgba(45,48,71,.5);backdrop-filter:blur(4px);z-index:999998;opacity:0;transition:opacity .3s}',
      '#axent-overlay.show{opacity:1}',
      '#axent-widget{position:fixed;z-index:999999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:480px;width:calc(100% - 32px)}',
      '#axent-widget.popup{top:50%;left:50%;transform:translate(-50%,-48%) scale(.95);opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1)}',
      '#axent-widget.popup.show{transform:translate(-50%,-50%) scale(1);opacity:1}',
      '#axent-widget.bottom-right{bottom:24px;right:24px;transform:translateY(20px);opacity:0;transition:all .35s ease}',
      '#axent-widget.bottom-right.show{transform:translateY(0);opacity:1}',
      '#axent-widget.bottom-bar{bottom:0;left:0;right:0;max-width:100%;width:100%;border-radius:20px 20px 0 0;transform:translateY(100%);opacity:0;transition:all .4s ease}',
      '#axent-widget.bottom-bar.show{transform:translateY(0);opacity:1}',
      '.axent-box{background:#fff;border-radius:var(--axent-radius);box-shadow:0 24px 64px rgba(45,48,71,.18);overflow:hidden}',
      '.axent-header{background:linear-gradient(135deg,var(--axent-primary),#ff8c42);padding:24px 28px 20px;position:relative}',
      '.axent-logo{display:flex;align-items:center;gap:10px;margin-bottom:8px}',
      '.axent-logo-icon{display:flex;align-items:center;justify-content:center}',
      '.axent-logo-text{color:#fff;font-size:16px;font-weight:700;letter-spacing:-.3px}',
      '.axent-title{color:#fff;font-size:22px;font-weight:800;margin:0 0 6px;line-height:1.2}',
      '.axent-desc{color:rgba(255,255,255,.85);font-size:14px;margin:0;line-height:1.5}',
      '.axent-body{padding:20px 28px}',
      '.axent-category{display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #f0f0f0}',
      '.axent-category:last-child{border-bottom:none}',
      '.axent-cat-info{flex:1}',
      '.axent-cat-name{font-size:14px;font-weight:700;color:var(--axent-dark);margin:0 0 3px}',
      '.axent-cat-desc{font-size:12px;color:#888;margin:0;line-height:1.4}',
      '.axent-toggle{position:relative;width:44px;height:24px;flex-shrink:0;margin-top:2px}',
      '.axent-toggle input{opacity:0;width:0;height:0;position:absolute}',
      '.axent-slider{position:absolute;inset:0;background:#ddd;border-radius:12px;cursor:pointer;transition:.25s}',
      '.axent-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2)}',
      '.axent-toggle input:checked + .axent-slider{background:var(--axent-primary)}',
      '.axent-toggle input:checked + .axent-slider:before{transform:translateX(20px)}',
      '.axent-toggle input:disabled + .axent-slider{background:var(--axent-secondary);cursor:not-allowed;opacity:.7}',
      '.axent-actions{padding:16px 28px 24px;display:flex;flex-direction:column;gap:10px}',
      '.axent-btn{border:none;border-radius:50px;padding:13px 24px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;width:100%;text-align:center}',
      '.axent-btn-primary{background:var(--axent-primary);color:#fff}',
      '.axent-btn-primary:hover{background:#e55a26;transform:translateY(-1px)}',
      '.axent-btn-secondary{background:#f5f5f5;color:var(--axent-dark)}',
      '.axent-btn-secondary:hover{background:#ebebeb}',
      '.axent-btn-outline{background:transparent;color:var(--axent-primary);border:2px solid var(--axent-primary)}',
      '.axent-btn-outline:hover{background:var(--axent-primary);color:#fff}',
      '.axent-footer{text-align:center;padding:0 28px 20px}',
      '.axent-powered{font-size:11px;color:#bbb;text-decoration:none;display:inline-flex;align-items:center;gap:4px}',
      '.axent-powered:hover{color:var(--axent-primary)}',
      '.axent-details{display:none;padding-top:12px}',
      '.axent-details.open{display:block}',
      '@media(max-width:520px){#axent-widget.popup{width:calc(100% - 24px)}.axent-header{padding:20px}.axent-body{padding:16px 20px}.axent-actions{padding:12px 20px 20px}}',
    ].join('');
    document.head.appendChild(style);
  }

  // ─── Construction du HTML ─────────────────────────────────────
  function buildWidget() {
    var pos = position;
    var html = [
      '<div class="axent-box">',
        '<div class="axent-header">',
          '<div class="axent-logo">',
            '<div class="axent-logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="36" height="36" alt=""></div>',
            '<span class="axent-logo-text">Axent</span>',
          '</div>',
          '<h2 class="axent-title">' + t.title + '</h2>',
          '<p class="axent-desc">' + t.desc + '</p>',
        '</div>',
        '<div class="axent-body">',
          buildCategory('essential', t.essential, t.essDesc, true, true),
          buildCategory('analytics', t.analytics, t.anaDesc, false, false),
          buildCategory('marketing', t.marketing, t.marDesc, false, false),
        '</div>',
        '<div class="axent-actions">',
          '<button class="axent-btn axent-btn-primary" id="axent-accept-all">' + t.accept + '</button>',
          '<button class="axent-btn axent-btn-secondary" id="axent-save">' + t.save + '</button>',
          '<button class="axent-btn axent-btn-outline" id="axent-refuse-all">' + t.refuse + '</button>',
        '</div>',
        '<div class="axent-footer">',
          '<a href="https://axet.fr" target="_blank" class="axent-powered">⚡ ' + t.poweredBy + '</a>',
        '</div>',
      '</div>',
    ].join('');

    // Overlay (pour popup seulement)
    var overlay = document.createElement('div');
    overlay.id  = 'axent-overlay';
    if (pos === 'popup') document.body.appendChild(overlay);

    var widget      = document.createElement('div');
    widget.id       = 'axent-widget';
    widget.className = pos;
    widget.innerHTML = html;
    document.body.appendChild(widget);

    // Animation d'apparition
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (pos === 'popup') overlay.classList.add('show');
        widget.classList.add('show');
      });
    });

    // Events
    document.getElementById('axent-accept-all').addEventListener('click', function () {
      saveConsent({ essential: true, analytics: true, marketing: true }, 'accepted');
    });
    document.getElementById('axent-refuse-all').addEventListener('click', function () {
      saveConsent({ essential: true, analytics: false, marketing: false }, 'refused');
    });
    document.getElementById('axent-save').addEventListener('click', function () {
      var choices = {
        essential: true,
        analytics: document.getElementById('axent-chk-analytics').checked,
        marketing: document.getElementById('axent-chk-marketing').checked,
      };
      var type = (choices.analytics || choices.marketing) ? 'partial' : 'refused';
      if (choices.analytics && choices.marketing) type = 'accepted';
      saveConsent(choices, type);
    });
  }

  function buildCategory(id, name, desc, checked, disabled) {
    return [
      '<div class="axent-category">',
        '<div class="axent-cat-info">',
          '<p class="axent-cat-name">' + name + (disabled ? ' 🔒' : '') + '</p>',
          '<p class="axent-cat-desc">' + desc + '</p>',
        '</div>',
        '<label class="axent-toggle">',
          '<input type="checkbox" id="axent-chk-' + id + '"',
            checked   ? ' checked'   : '',
            disabled  ? ' disabled'  : '',
          '>',
          '<span class="axent-slider"></span>',
        '</label>',
      '</div>',
    ].join('');
  }

  // ─── Sauvegarde & fermeture ───────────────────────────────────
  function saveConsent(choices, type) {
    var data = {
      v:          versionId,
      choices:    choices,
      type:       type,
      ts:         Date.now(),
    };
    setCookie(COOKIE_NAME, JSON.stringify(data), COOKIE_DAYS);
    applyGCM(choices);
    sendConsent(choices, type);
    closeWidget();

    // Callback personnalisé
    if (typeof settings.onConsent === 'function') {
      settings.onConsent(choices, type);
    }

    // Événement DOM
    document.dispatchEvent(new CustomEvent('axent:consent', { detail: { choices: choices, type: type } }));
  }

  function closeWidget() {
    var widget  = document.getElementById('axent-widget');
    var overlay = document.getElementById('axent-overlay');
    if (widget)  { widget.classList.remove('show');  setTimeout(function(){ widget.remove(); },  400); }
    if (overlay) { overlay.classList.remove('show'); setTimeout(function(){ overlay.remove(); }, 400); }
  }

  // ─── Point d'entrée ───────────────────────────────────────────
  function init() {
    if (!clientId) { console.warn('[Axent] clientId manquant dans axentSettings'); return; }

    initGCM();

    // Déjà consenti ?
    var consent = getConsent();
    if (consent && consent.v === versionId) {
      applyGCM(consent.choices);
      return;
    }

    // Charger et afficher le widget
    injectStyles();

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', buildWidget);
    } else {
      buildWidget();
    }
  }

  // ─── API publique ─────────────────────────────────────────────
  window.Axent = {
    getConsent: getConsent,
    hasConsent: function (category) {
      var c = getConsent();
      return c && c.choices && c.choices[category] === true;
    },
    reset: function () {
      document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
      location.reload();
    },
  };

  init();

})(window, document);
