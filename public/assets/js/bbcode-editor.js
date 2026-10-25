(function () {
  "use strict";

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function insertText(ta, text, start, end) {
    var before = ta.value.substring(0, start);
    var after = ta.value.substring(end);
    ta.value = before + text + after;

    var pos = start + text.length;
    try {
      ta.focus();
      ta.setSelectionRange(pos, pos);
    } catch (e) {}

    try {
      ta.dispatchEvent(new Event("input", { bubbles: true }));
    } catch (e) {}
  }

  function setupRoot(root) {
    if (!root || root.getAttribute("data-bbcode-ready") === "1") return;

    var ta = root.querySelector("textarea[data-bbcode-editor]");
    if (!ta) return;

    root.setAttribute("data-bbcode-ready", "1");

    var status = root.querySelector("[data-bbcode-status]");
    if (status) {
      status.innerHTML = "Editor BBCode listo";
      status.className = "bbcode-js-status ready";
    }

    var buttons = root.querySelectorAll("[data-bbtag]");
    for (var i = 0; i < buttons.length; i++) {
      (function (btn) {
        btn.addEventListener("click", function (ev) {
          if (ev && ev.preventDefault) ev.preventDefault();

          var tag = btn.getAttribute("data-bbtag") || "";
          var val = btn.getAttribute("data-bbvalue") || "";
          var start = typeof ta.selectionStart === "number" ? ta.selectionStart : ta.value.length;
          var end = typeof ta.selectionEnd === "number" ? ta.selectionEnd : start;
          var selected = ta.value.substring(start, end);
          var text = "";

          if (tag === "item") {
            text = "[*] " + selected;
          } else if (tag === "hr") {
            text = "[hr]";
          } else if (tag === "youtube") {
            var youtube = selected || window.prompt(
              "Pega la URL o ID del video de YouTube:",
              "https://www.youtube.com/watch?v="
            );
            if (!youtube) return;
            text = "[youtube]" + youtube.replace(/^\s+|\s+$/g, "") + "[/youtube]";
          } else if (tag === "img") {
            var image = selected || window.prompt("URL HTTPS de la imagen:", "https://");
            if (!image) return;
            text = "[img]" + image.replace(/^\s+|\s+$/g, "") + "[/img]";
          } else if (tag === "url") {
            var href = "";
            var label = selected;

            if (selected && /^https?:\/\//i.test(selected)) {
              href = selected;
              label = selected;
            } else {
              href = window.prompt("URL del enlace:", "https://") || "";
              if (!href) return;
              if (!label) {
                label = window.prompt("Texto del enlace:", href) || href;
              }
            }

            text = "[url=" + href + "]" + label + "[/url]";
          } else if (tag === "list") {
            text = "[list]\n[*] " + (selected || "Primer elemento") +
                   "\n[*] Segundo elemento\n[/list]";
          } else {
            var open = "[" + tag + (val ? "=" + val : "") + "]";
            var close = "[/" + tag + "]";
            text = open + selected + close;
          }

          insertText(ta, text, start, end);
        });
      })(buttons[i]);
    }
  }

  function youtubeId(value) {
    value = (value || "").replace(/^\s+|\s+$/g, "");

    var direct = value.match(/^[A-Za-z0-9_-]{11}$/);
    if (direct) return value;

    var m = value.match(
      /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i
    );
    return m ? m[1] : null;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function renderMediaPreview(ta, box) {
    if (!ta || !box) return;

    var text = ta.value || "";
    var lines = text.split(/\r?\n/);
    var html = "";
    var seen = {};

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i].replace(/^\s+|\s+$/g, "");
      if (!line) continue;

      var y = null;

      var youtubeTag = line.match(/^\[youtube\](.*?)\[\/youtube\]$/i);
      if (youtubeTag) {
        y = youtubeId(youtubeTag[1]);
      } else if (/^https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\//i.test(line)) {
        y = youtubeId(line);
      }

      if (y && !seen["yt:" + y]) {
        seen["yt:" + y] = true;
        html +=
          '<div class="bbcode-video">' +
          '<iframe src="https://www.youtube-nocookie.com/embed/' +
          encodeURIComponent(y) +
          '" title="YouTube preview" loading="lazy" allowfullscreen></iframe>' +
          "</div>";
        continue;
      }

      var img = null;
      var imgTag = line.match(/^\[img\](https:\/\/[^\s]+)\[\/img\]$/i);
      if (imgTag) {
        img = imgTag[1];
      } else if (/^https:\/\/[^\s]+\.(?:png|jpe?g|gif|webp|avif)(?:\?[^\s]*)?$/i.test(line)) {
        img = line;
      }

      if (img && !seen["img:" + img]) {
        seen["img:" + img] = true;
        html +=
          '<img class="bbcode-image" src="' +
          escapeHtml(img) +
          '" alt="Vista previa" loading="lazy">';
      }
    }

    if (!html) {
      box.innerHTML =
        '<div class="bbcode-preview-empty">No hay videos o imágenes detectados todavía.</div>';
    } else {
      box.innerHTML = html;
    }
  }

  function installMediaPreview(root) {
    if (!root) return;

    var ta = root.querySelector("textarea[data-bbcode-editor]");
    var box = root.querySelector("[data-bbcode-media-preview]");
    if (!ta || !box) return;

    var timer = null;
    function update() {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        renderMediaPreview(ta, box);
      }, 180);
    }

    ta.addEventListener("input", update);
    update();
  }

  function initBbcode() {
    var roots = document.querySelectorAll("[data-bbcode-root]");
    for (var i = 0; i < roots.length; i++) {
      setupRoot(roots[i]);
      installMediaPreview(roots[i]);
    }

    /* Changelog title preview */
    var form = document.querySelector(".changelog-admin-form");
    var preview = document.getElementById("changelog-title-preview");
    if (form && preview) {
      var title = form.querySelector("[name=title]");

      function updatePreview() {
        if (!title) return;
        preview.innerHTML = "";
        preview.appendChild(document.createTextNode(title.value || "Título de actualización"));

        var tc = form.querySelector("[name=title_color]");
        var bg = form.querySelector("[name=title_bg_color]");
        var bc = form.querySelector("[name=title_border_color]");
        var bw = form.querySelector("[name=title_border_width]");
        var sc = form.querySelector("[name=title_shadow_color]");
        var ss = form.querySelector("[name=title_shadow_size]");

        if (tc) preview.style.color = tc.value;
        if (bg) preview.style.background = bg.value;
        if (bc && bw) preview.style.border = bw.value + "px solid " + bc.value;
        if (sc && ss) {
          preview.style.textShadow =
            ss.value === "0" ? "none" : "0 0 " + ss.value + "px " + sc.value;
        }
      }

      var controls = form.querySelectorAll(
        "[name=title],[name=title_color],[name=title_bg_color]," +
        "[name=title_border_color],[name=title_border_width]," +
        "[name=title_shadow_color],[name=title_shadow_size]"
      );

      for (var j = 0; j < controls.length; j++) {
        controls[j].addEventListener("input", updatePreview);
      }

      updatePreview();
    }
  }

  ready(initBbcode);
})();