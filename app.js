/* ═══════════════════════════════════════════════════
   HedgeDoc Markdown Editor & Viewer – Application
   ═══════════════════════════════════════════════════ */

(function () {
  "use strict";

  // ── DOM refs ──────────────────────────────────────
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

  const input = $("#markdown-input");
  const preview = $("#markdown-preview");
  const workspace = $(".workspace");
  const lineNumbers = $("#line-numbers");
  const tocSidebar = $("#toc-sidebar");
  const tocList = $("#toc-list");
  const dropOverlay = $("#drop-overlay");

  const copyBtn = $("#copy-markdown");
  const downloadMdBtn = $("#download-md");
  const downloadHtmlBtn = $("#download-html");
  const toggleTocBtn = $("#toggle-toc");
  const closeTocBtn = $("#close-toc");
  const toggleThemeBtn = $("#toggle-theme");

  const statLines = $("#stat-lines");
  const statWords = $("#stat-words");
  const statChars = $("#stat-chars");
  const statCursor = $("#stat-cursor");
  const saveStatus = $("#save-status");

  // ── Default content ───────────────────────────────
  const defaultMarkdown = `# Welcome to HedgeDoc

Write **Markdown** on the left, see a live **preview** on the right.

## Features

- **Live preview** with GitHub Flavored Markdown
- **Syntax highlighting** for code blocks
- **Dark mode** toggle (click the sun icon)
- **Three view modes**: Split, Edit-only, Preview-only
- **Table of Contents** sidebar (click the list icon)
- **Drag & drop** \`.md\` or \`.txt\` files onto the editor
- **Auto-save** to browser localStorage
- **Download** as \`.md\` or \`.html\`
- **Resizable panels** — drag the divider between editor and preview
- **Keyboard shortcuts** for common formatting

## Formatting Examples

### Text Styles

**Bold text**, *italic text*, ~~strikethrough~~, \`inline code\`

### Links & Images

[HedgeDoc](https://hedgedoc.org) — open-source collaborative markdown

### Blockquote

> "The best way to predict the future is to invent it."
> — Alan Kay

### Code Block

\`\`\`javascript
function greet(name) {
  return \`Hello, \${name}! Welcome to HedgeDoc.\`;
}

console.log(greet("World"));
\`\`\`

### Task List

- [x] Set up markdown editor
- [x] Add live preview
- [x] Implement dark mode
- [ ] Add collaborative editing
- [ ] Deploy to production

### Table

| Feature         | Status |
|-----------------|--------|
| Live Preview    | Done   |
| Dark Mode       | Done   |
| Syntax Highlight| Done   |
| Table of Contents| Done  |
| File Drag & Drop| Done   |

---

### Keyboard Shortcuts

| Shortcut       | Action             |
|----------------|--------------------|
| Ctrl + B       | Bold               |
| Ctrl + I       | Italic             |
| Ctrl + S       | Download .md       |
| Ctrl + Shift+S | Download .html     |
| Ctrl + Alt + 1 | Split view         |
| Ctrl + Alt + 2 | Edit view          |
| Ctrl + Alt + 3 | Preview view       |
| Tab            | Insert indent      |
| Shift + Tab    | Remove indent      |

---

*Start editing to see changes reflected in real time!*
`;

  // ── Marked configuration ──────────────────────────
  const renderer = new marked.Renderer();

  // Custom code renderer for syntax highlighting via highlight.js
  renderer.code = function (code, language) {
    // marked v14+ passes an object; v4-v13 passes (code, lang) strings
    let text = code;
    let lang = language;
    if (typeof code === "object" && code !== null) {
      text = code.text || "";
      lang = code.lang || "";
    }
    let highlighted;
    if (lang && hljs.getLanguage(lang)) {
      try {
        highlighted = hljs.highlight(text, { language: lang }).value;
      } catch (_) {
        highlighted = text;
      }
    } else {
      try {
        highlighted = hljs.highlightAuto(text).value;
      } catch (_) {
        highlighted = text;
      }
    }
    const langClass = lang ? ` class="language-${lang}"` : "";
    return `<pre><code${langClass}>${highlighted}</code></pre>`;
  };

  marked.setOptions({
    gfm: true,
    breaks: true,
    renderer,
  });

  // ── State ─────────────────────────────────────────
  const STORAGE_KEY = "hedgedoc_content";
  const THEME_KEY = "hedgedoc_theme";
  const VIEW_KEY = "hedgedoc_view";
  let saveTimeout = null;
  let scrollSyncEnabled = true;
  let isEditorScrolling = false;
  let isPreviewScrolling = false;

  // ── Init ──────────────────────────────────────────
  function init() {
    loadTheme();
    loadViewMode();
    loadContent();
    updatePreview();
    updateLineNumbers();
    updateStats();
    bindEvents();
  }

  // ── Theme ─────────────────────────────────────────
  function loadTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === "dark") {
      applyTheme("dark");
    }
  }

  function applyTheme(theme) {
    const isDark = theme === "dark";
    document.documentElement.setAttribute("data-theme", isDark ? "dark" : "");
    const sunIcon = toggleThemeBtn.querySelector(".icon-sun");
    const moonIcon = toggleThemeBtn.querySelector(".icon-moon");
    sunIcon.style.display = isDark ? "none" : "";
    moonIcon.style.display = isDark ? "" : "none";

    // Toggle highlight.js stylesheets
    const lightSheet = $("#hljs-light");
    const darkSheet = $("#hljs-dark");
    if (lightSheet) lightSheet.disabled = isDark;
    if (darkSheet) darkSheet.disabled = !isDark;

    localStorage.setItem(THEME_KEY, isDark ? "dark" : "light");
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute("data-theme");
    applyTheme(current === "dark" ? "light" : "dark");
  }

  // ── View mode ─────────────────────────────────────
  function loadViewMode() {
    const saved = localStorage.getItem(VIEW_KEY) || "split";
    setViewMode(saved);
  }

  function setViewMode(mode) {
    workspace.setAttribute("data-view", mode);
    $$(".view-btn").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.view === mode);
    });
    localStorage.setItem(VIEW_KEY, mode);
  }

  // ── Content persistence ───────────────────────────
  function loadContent() {
    const saved = localStorage.getItem(STORAGE_KEY);
    input.value = saved !== null ? saved : defaultMarkdown;
    markSaved();
  }

  function scheduleAutoSave() {
    clearTimeout(saveTimeout);
    saveStatus.textContent = "Unsaved";
    saveStatus.classList.remove("saved");
    saveTimeout = setTimeout(() => {
      localStorage.setItem(STORAGE_KEY, input.value);
      markSaved();
    }, 800);
  }

  function markSaved() {
    saveStatus.textContent = "Saved";
    saveStatus.classList.add("saved");
  }

  // ── Markdown → HTML ───────────────────────────────
  function updatePreview() {
    const raw = input.value;
    const html = marked.parse(raw);
    preview.innerHTML = DOMPurify.sanitize(html);
    updateToc();
  }

  // ── Line numbers ──────────────────────────────────
  function updateLineNumbers() {
    const lines = input.value.split("\n").length;
    let html = "";
    for (let i = 1; i <= lines; i++) {
      html += `<span>${i}</span>`;
    }
    lineNumbers.innerHTML = html;
  }

  function syncLineNumberScroll() {
    lineNumbers.scrollTop = input.scrollTop;
  }

  // ── Stats ─────────────────────────────────────────
  function updateStats() {
    const text = input.value;
    const lines = text.split("\n").length;
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    const chars = text.length;

    statLines.textContent = `${lines} line${lines !== 1 ? "s" : ""}`;
    statWords.textContent = `${words} word${words !== 1 ? "s" : ""}`;
    statChars.textContent = `${chars} char${chars !== 1 ? "s" : ""}`;
  }

  function updateCursorPos() {
    const text = input.value.substring(0, input.selectionStart);
    const lines = text.split("\n");
    const ln = lines.length;
    const col = lines[lines.length - 1].length + 1;
    statCursor.textContent = `Ln ${ln}, Col ${col}`;
  }

  // ── Table of Contents ─────────────────────────────
  function updateToc() {
    const headings = preview.querySelectorAll("h1, h2, h3, h4");
    tocList.innerHTML = "";
    headings.forEach((h, i) => {
      const level = parseInt(h.tagName[1], 10);
      const id = `heading-${i}`;
      h.id = id;

      const a = document.createElement("a");
      a.href = `#${id}`;
      a.textContent = h.textContent;
      a.setAttribute("data-level", level);
      a.addEventListener("click", (e) => {
        e.preventDefault();
        h.scrollIntoView({ behavior: "smooth", block: "start" });
      });
      tocList.appendChild(a);
    });
  }

  // ── Text insertion helpers ────────────────────────
  function wrapSelection(before, after) {
    if (after === undefined) after = before;
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const text = input.value;
    const selected = text.slice(start, end);
    const replacement = before + selected + after;
    input.setRangeText(replacement, start, end, "select");
    input.focus();
    // Place cursor after if no selection, inside if selection existed
    if (start === end) {
      input.setSelectionRange(start + before.length, start + before.length);
    } else {
      input.setSelectionRange(start, start + replacement.length);
    }
    onInput();
  }

  function insertLinePrefix(prefix) {
    const start = input.selectionStart;
    const text = input.value;
    // Find start of current line
    const lineStart = text.lastIndexOf("\n", start - 1) + 1;
    input.setRangeText(prefix, lineStart, lineStart, "end");
    input.focus();
    onInput();
  }

  function insertAtCursor(text) {
    const start = input.selectionStart;
    input.setRangeText(text, start, input.selectionEnd, "end");
    input.focus();
    onInput();
  }

  // ── Toolbar actions ───────────────────────────────
  const toolbarActions = {
    h1: () => insertLinePrefix("# "),
    h2: () => insertLinePrefix("## "),
    h3: () => insertLinePrefix("### "),
    bold: () => wrapSelection("**"),
    italic: () => wrapSelection("*"),
    strikethrough: () => wrapSelection("~~"),
    code: () => wrapSelection("`"),
    codeblock: () => wrapSelection("\n```\n", "\n```\n"),
    quote: () => insertLinePrefix("> "),
    ul: () => insertLinePrefix("- "),
    ol: () => insertLinePrefix("1. "),
    task: () => insertLinePrefix("- [ ] "),
    link: () => {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      const selected = input.value.slice(start, end);
      if (selected) {
        wrapSelection("[", "](url)");
      } else {
        insertAtCursor("[link text](url)");
      }
    },
    image: () => insertAtCursor("![alt text](image-url)"),
    table: () =>
      insertAtCursor(
        "\n| Header 1 | Header 2 | Header 3 |\n|----------|----------|----------|\n| Cell 1   | Cell 2   | Cell 3   |\n| Cell 4   | Cell 5   | Cell 6   |\n"
      ),
    hr: () => insertAtCursor("\n---\n"),
  };

  // ── Scroll sync ───────────────────────────────────
  function syncEditorToPreview() {
    if (isPreviewScrolling) return;
    isEditorScrolling = true;
    const ratio =
      input.scrollTop / (input.scrollHeight - input.clientHeight || 1);
    preview.scrollTop =
      ratio * (preview.scrollHeight - preview.clientHeight || 1);
    requestAnimationFrame(() => {
      isEditorScrolling = false;
    });
  }

  function syncPreviewToEditor() {
    if (isEditorScrolling) return;
    isPreviewScrolling = true;
    const ratio =
      preview.scrollTop / (preview.scrollHeight - preview.clientHeight || 1);
    input.scrollTop = ratio * (input.scrollHeight - input.clientHeight || 1);
    requestAnimationFrame(() => {
      isPreviewScrolling = false;
    });
  }

  // ── Gutter resize ─────────────────────────────────
  function initGutterResize() {
    const gutter = $("#gutter");
    let startX, startEditorWidth;

    function onMouseDown(e) {
      e.preventDefault();
      const editorPane = $(".editor-pane");
      startX = e.clientX;
      startEditorWidth = editorPane.getBoundingClientRect().width;
      gutter.classList.add("dragging");
      document.addEventListener("mousemove", onMouseMove);
      document.addEventListener("mouseup", onMouseUp);
    }

    function onMouseMove(e) {
      const editorPane = $(".editor-pane");
      const previewPane = $(".preview-pane");
      const workspaceWidth = workspace.getBoundingClientRect().width;
      const tocWidth = tocSidebar.classList.contains("open")
        ? tocSidebar.getBoundingClientRect().width
        : 0;
      const available = workspaceWidth - tocWidth - 5; // 5 = gutter width
      const diff = e.clientX - startX;
      const newEditorWidth = Math.max(
        200,
        Math.min(available - 200, startEditorWidth + diff)
      );
      const editorFlex = newEditorWidth / available;
      const previewFlex = 1 - editorFlex;
      editorPane.style.flex = `${editorFlex} 0 0`;
      previewPane.style.flex = `${previewFlex} 0 0`;
    }

    function onMouseUp() {
      gutter.classList.remove("dragging");
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    }

    gutter.addEventListener("mousedown", onMouseDown);
  }

  // ── File drag & drop ──────────────────────────────
  function initDragDrop() {
    let dragCounter = 0;

    document.addEventListener("dragenter", (e) => {
      e.preventDefault();
      dragCounter++;
      dropOverlay.classList.add("visible");
    });

    document.addEventListener("dragleave", (e) => {
      e.preventDefault();
      dragCounter--;
      if (dragCounter <= 0) {
        dragCounter = 0;
        dropOverlay.classList.remove("visible");
      }
    });

    document.addEventListener("dragover", (e) => {
      e.preventDefault();
    });

    document.addEventListener("drop", (e) => {
      e.preventDefault();
      dragCounter = 0;
      dropOverlay.classList.remove("visible");

      const file = e.dataTransfer.files[0];
      if (!file) return;

      const validTypes = [
        "text/markdown",
        "text/plain",
        "text/x-markdown",
        "",
      ];
      const validExts = [".md", ".markdown", ".txt", ".text"];
      const ext = "." + file.name.split(".").pop().toLowerCase();

      if (validTypes.includes(file.type) || validExts.includes(ext)) {
        const reader = new FileReader();
        reader.onload = (evt) => {
          input.value = evt.target.result;
          onInput();
          input.scrollTop = 0;
        };
        reader.readAsText(file);
      }
    });
  }

  // ── Download helpers ──────────────────────────────
  function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function downloadMarkdown() {
    downloadFile(input.value, "document.md", "text/markdown");
  }

  function downloadHtml() {
    const html = marked.parse(input.value);
    const sanitized = DOMPurify.sanitize(html);
    const fullHtml = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Markdown Export</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; line-height: 1.7; color: #1a1d23; }
    pre { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
    pre code { background: none; padding: 0; }
    blockquote { border-left: 4px solid #2563eb; margin: 12px 0; padding: 8px 16px; color: #6b7280; background: #f8fafc; border-radius: 0 8px 8px 0; }
    table { border-collapse: collapse; width: 100%; margin: 12px 0; }
    th, td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; }
    th { background: rgba(37, 99, 235, 0.1); font-weight: 600; }
    img { max-width: 100%; }
    hr { border: none; height: 2px; background: #e5e7eb; margin: 24px 0; }
    h1, h2 { border-bottom: 1px solid #e5e7eb; padding-bottom: 0.3em; }
  </style>
</head>
<body>
${sanitized}
</body>
</html>`;
    downloadFile(fullHtml, "document.html", "text/html");
  }

  // ── Copy to clipboard ─────────────────────────────
  async function copyMarkdown() {
    try {
      await navigator.clipboard.writeText(input.value);
      copyBtn.textContent = "Copied!";
      setTimeout(() => {
        copyBtn.textContent = "Copy MD";
      }, 1500);
    } catch (err) {
      // Fallback for non-secure contexts
      input.select();
      document.execCommand("copy");
      copyBtn.textContent = "Copied!";
      setTimeout(() => {
        copyBtn.textContent = "Copy MD";
      }, 1500);
    }
  }

  // ── Central input handler ─────────────────────────
  function onInput() {
    updatePreview();
    updateLineNumbers();
    updateStats();
    scheduleAutoSave();
  }

  // ── Tab handling ──────────────────────────────────
  function handleTab(e) {
    e.preventDefault();
    const start = input.selectionStart;
    const end = input.selectionEnd;

    if (e.shiftKey) {
      // Outdent: remove leading 2 spaces from selected lines
      const text = input.value;
      const lineStart = text.lastIndexOf("\n", start - 1) + 1;
      const lineEnd = text.indexOf("\n", end);
      const endIdx = lineEnd === -1 ? text.length : lineEnd;
      const block = text.slice(lineStart, endIdx);
      const dedented = block
        .split("\n")
        .map((line) => (line.startsWith("  ") ? line.slice(2) : line))
        .join("\n");
      input.setRangeText(dedented, lineStart, endIdx, "select");
      input.setSelectionRange(lineStart, lineStart + dedented.length);
    } else if (start === end) {
      // No selection: insert 2 spaces
      input.setRangeText("  ", start, end, "end");
    } else {
      // Indent selected lines
      const text = input.value;
      const lineStart = text.lastIndexOf("\n", start - 1) + 1;
      const lineEnd = text.indexOf("\n", end);
      const endIdx = lineEnd === -1 ? text.length : lineEnd;
      const block = text.slice(lineStart, endIdx);
      const indented = block
        .split("\n")
        .map((line) => "  " + line)
        .join("\n");
      input.setRangeText(indented, lineStart, endIdx, "select");
      input.setSelectionRange(lineStart, lineStart + indented.length);
    }
    onInput();
  }

  // ── Keyboard shortcuts ────────────────────────────
  function handleKeyDown(e) {
    const mod = e.ctrlKey || e.metaKey;

    // Tab
    if (e.key === "Tab") {
      handleTab(e);
      e.stopPropagation();
      return;
    }

    // Ctrl+B — Bold
    if (mod && e.key === "b") {
      e.preventDefault();
      e.stopPropagation();
      toolbarActions.bold();
      return;
    }

    // Ctrl+I — Italic
    if (mod && e.key === "i") {
      e.preventDefault();
      e.stopPropagation();
      toolbarActions.italic();
      return;
    }

    // Ctrl+S — Download .md
    if (mod && !e.shiftKey && e.key === "s") {
      e.preventDefault();
      e.stopPropagation();
      downloadMarkdown();
      return;
    }

    // Ctrl+Shift+S — Download .html
    if (mod && e.shiftKey && e.key === "S") {
      e.preventDefault();
      e.stopPropagation();
      downloadHtml();
      return;
    }

    // Ctrl+Alt+1/2/3 — View modes
    if (mod && e.altKey) {
      e.stopPropagation();
      if (e.key === "1") {
        e.preventDefault();
        setViewMode("split");
      } else if (e.key === "2") {
        e.preventDefault();
        setViewMode("edit");
      } else if (e.key === "3") {
        e.preventDefault();
        setViewMode("preview");
      }
    }
  }

  // ── Bind all events ───────────────────────────────
  function bindEvents() {
    // Input
    input.addEventListener("input", onInput);
    input.addEventListener("scroll", () => {
      syncLineNumberScroll();
      if (scrollSyncEnabled) syncEditorToPreview();
    });
    input.addEventListener("keydown", handleKeyDown);
    input.addEventListener("click", updateCursorPos);
    input.addEventListener("keyup", updateCursorPos);

    // Preview scroll sync
    preview.addEventListener("scroll", () => {
      if (scrollSyncEnabled) syncPreviewToEditor();
    });

    // View mode buttons
    $$(".view-btn").forEach((btn) => {
      btn.addEventListener("click", () => setViewMode(btn.dataset.view));
    });

    // Toolbar buttons
    $$("[data-action]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const action = toolbarActions[btn.dataset.action];
        if (action) action();
      });
    });

    // Header actions
    copyBtn.addEventListener("click", copyMarkdown);
    downloadMdBtn.addEventListener("click", downloadMarkdown);
    downloadHtmlBtn.addEventListener("click", downloadHtml);

    // Theme toggle
    toggleThemeBtn.addEventListener("click", toggleTheme);

    // ToC toggle
    toggleTocBtn.addEventListener("click", () => {
      tocSidebar.classList.toggle("open");
    });
    closeTocBtn.addEventListener("click", () => {
      tocSidebar.classList.remove("open");
    });

    // Global keyboard shortcuts (for when focus is not in textarea)
    document.addEventListener("keydown", (e) => {
      const mod = e.ctrlKey || e.metaKey;
      if (mod && !e.shiftKey && e.key === "s") {
        e.preventDefault();
        downloadMarkdown();
      }
      if (mod && e.shiftKey && e.key === "S") {
        e.preventDefault();
        downloadHtml();
      }
      if (mod && e.altKey) {
        if (e.key === "1") {
          e.preventDefault();
          setViewMode("split");
        } else if (e.key === "2") {
          e.preventDefault();
          setViewMode("edit");
        } else if (e.key === "3") {
          e.preventDefault();
          setViewMode("preview");
        }
      }
    });

    // Gutter resize
    initGutterResize();

    // Drag & drop
    initDragDrop();
  }

  // ── Start ─────────────────────────────────────────
  init();
})();
