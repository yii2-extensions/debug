(function () {
  var btn = document.querySelector("[data-yii-debug-theme-toggle]");
  if (!btn) {
    return;
  }
  var iconSlot = btn.querySelector(".yii-debug-brand-icon");
  btn.addEventListener("click", function () {
    var html = document.documentElement;
    var current =
      html.getAttribute("data-yii-debug-theme") ||
      btn.getAttribute("data-current-theme") ||
      "light";
    var next = current === "dark" ? "light" : "dark";
    html.setAttribute("data-yii-debug-theme", next);
    btn.setAttribute("data-current-theme", next);
    if (iconSlot) {
      iconSlot.innerHTML =
        next === "dark"
          ? btn.getAttribute("data-icon-sun")
          : btn.getAttribute("data-icon-moon");
    }
    try {
      localStorage.setItem("yii-debug-toolbar-theme", next);
    } catch (_e) {}
    document.cookie =
      "yii-debug-toolbar-theme=" +
      next +
      ";path=/;max-age=31536000;samesite=lax";
    if (window.parent && window.parent !== window) {
      try {
        window.parent.postMessage(
          { source: "yii-debug-toolbar", type: "theme", theme: next },
          "*",
        );
      } catch (_e) {}
    }
  });
})();
