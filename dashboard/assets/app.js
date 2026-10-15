(function () {
  "use strict";

  var currentScript = document.currentScript;
  var fallbackPath = "../web/assets/app.js";
  var targetSrc = fallbackPath;

  if (currentScript && currentScript.src) {
    targetSrc = currentScript.src.replace(/\/dashboard\/assets\/app\.js(\?.*)?$/, "/web/assets/app.js$1");
  }

  var script = document.createElement("script");
  script.src = targetSrc;
  script.defer = true;
  document.head.appendChild(script);
})();
