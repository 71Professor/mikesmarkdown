/* ═══════════════════════════════════════════════════
   HedgeDoc Markdown Notes – Application
   Multi-document management with MySQL backend
   ═══════════════════════════════════════════════════ */

(function () {
  "use strict";

  // ── DOM refs ──────────────────────────────────────
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

  // Auth refs
  const authPage = $("#auth-page");
  const mainApp = $("#main-app");
  const loginForm = $("#login-form");
  const registerForm = $("#register-form");
  const loginError = $("#login-error");
  const registerError = $("#register-error");
  const showRegisterBtn = $("#show-register");
  const showLoginBtn = $("#show-login");
  const userGreeting = $("#user-greeting");
  const btnLogout = $("#btn-logout");

  // App refs
  const app = mainApp;
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
  const toggleThemeEditorBtn = $("#toggle-theme-editor");

  const statLines = $("#stat-lines");
  const statWords = $("#stat-words");
  const statChars = $("#stat-chars");
  const statCursor = $("#stat-cursor");
  const saveStatus = $("#save-status");

  // Overview refs
  const notesGrid = $("#notes-grid");
  const notesEmpty = $("#notes-empty");
  const searchInput = $("#search-notes");
  const btnNewNote = $("#btn-new-note");
  const btnNewNoteEmpty = $("#btn-new-note-empty");
  const btnBackOverview = $("#btn-back-overview");

  // Tag filter refs
  const tagFilterBar = $("#tag-filter-bar");

  // Delete modal refs
  const deleteModal = $("#delete-modal");
  const deleteModalText = $("#delete-modal-text");
  const deleteConfirmBtn = $("#delete-confirm");
  const deleteCancelBtn = $("#delete-cancel");

  // ── Constants ─────────────────────────────────────
  const API = "api.php";
  const THEME_KEY = "hedgedoc_theme";
  const VIEW_KEY = "hedgedoc_view";
  const SORT_KEY = "hedgedoc_sort";

  // ── State ─────────────────────────────────────────
  let currentUser = null;
  let currentDocId = null;
  let saveTimeout = null;
  let scrollSyncEnabled = true;
  let isEditorScrolling = false;
  let isPreviewScrolling = false;
  let currentSort = localStorage.getItem(SORT_KEY) || "lastAccessed";
  let deleteTargetId = null;
  let cachedNotes = []; // cached list for client-side sorting
  let activeTagFilter = null; // currently selected tag for filtering

  // ── Marked configuration ──────────────────────────
  const renderer = new marked.Renderer();

  renderer.code = function (code, language) {
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

  // ══════════════════════════════════════════════════
  //  API Client
  // ══════════════════════════════════════════════════

  async function apiGet(action, params = {}) {
    const url = new URL(API, window.location.href);
    url.searchParams.set("action", action);
    for (const [k, v] of Object.entries(params)) {
      url.searchParams.set(k, v);
    }
    const res = await fetch(url, { credentials: "same-origin" });
    const json = await res.json();
    if (!json.success) {
      if (res.status === 401) {
        handleSessionExpired();
      }
      throw new Error(json.error || "API error");
    }
    return json.data;
  }

  async function apiPost(action, data = {}) {
    const res = await fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action, ...data }),
      credentials: "same-origin",
    });
    const json = await res.json();
    if (!json.success) {
      if (res.status === 401 && action !== "login" && action !== "register") {
        handleSessionExpired();
      }
      throw new Error(json.error || "API error");
    }
    return json.data;
  }

  function handleSessionExpired() {
    currentUser = null;
    authPage.style.display = "";
    mainApp.style.display = "none";
  }

  function extractTitle(markdown) {
    const match = markdown.match(/^#\s+(.+)$/m);
    if (match) return match[1].trim();
    const lines = markdown.split("\n");
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed) return trimmed.slice(0, 60);
    }
    return "Unbenannte Notiz";
  }

  // ══════════════════════════════════════════════════
  //  Authentication
  // ══════════════════════════════════════════════════

  function showAuthError(el, message) {
    el.textContent = message;
    el.style.display = "";
  }

  function hideAuthError(el) {
    el.textContent = "";
    el.style.display = "none";
  }

  async function checkSession() {
    try {
      const data = await apiGet("session");
      if (data) {
        currentUser = data;
        showApp();
      } else {
        showAuth();
      }
    } catch (err) {
      showAuth();
    }
  }

  function showAuth() {
    authPage.style.display = "";
    mainApp.style.display = "none";
  }

  function showApp() {
    authPage.style.display = "none";
    mainApp.style.display = "";
    userGreeting.textContent = currentUser.username;
    startApp();
  }

  async function handleLogin(e) {
    e.preventDefault();
    hideAuthError(loginError);

    const email = $("#login-email").value.trim();
    const password = $("#login-password").value;

    try {
      const data = await apiPost("login", { email, password });
      currentUser = data;
      loginForm.reset();
      showApp();
    } catch (err) {
      showAuthError(loginError, err.message);
    }
  }

  async function handleRegister(e) {
    e.preventDefault();
    hideAuthError(registerError);

    const username = $("#register-username").value.trim();
    const email = $("#register-email").value.trim();
    const password = $("#register-password").value;

    try {
      const data = await apiPost("register", { username, email, password });
      currentUser = data;
      registerForm.reset();
      showApp();
    } catch (err) {
      showAuthError(registerError, err.message);
    }
  }

  async function handleLogout() {
    try {
      await apiPost("logout");
    } catch (_) {
      // ignore errors
    }
    currentUser = null;
    currentDocId = null;
    showAuth();
  }

  // ══════════════════════════════════════════════════
  //  Overview / Dashboard
  // ══════════════════════════════════════════════════

  function formatDate(dateString) {
    const d = new Date(dateString.includes("T") ? dateString : dateString + "Z");
    const options = {
      weekday: "short",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    };
    return d.toLocaleDateString("de-DE", options);
  }

  let searchDebounce = null;

  async function renderOverview() {
    const query = searchInput.value.trim();

    try {
      const params = {};
      if (query) params.q = query;
      cachedNotes = await apiGet("list", params);
    } catch (err) {
      console.error("Failed to load notes:", err);
      notesGrid.innerHTML = '<div class="notes-empty"><p>Fehler beim Laden der Notizen.</p></div>';
      return;
    }

    // Collect all unique tags for the filter bar
    const allTags = new Set();
    cachedNotes.forEach((doc) => {
      (doc.tags || []).forEach((tag) => allTags.add(tag));
    });
    renderTagFilterBar(allTags);

    // If a tag filter no longer exists in the data, clear it
    if (activeTagFilter && !allTags.has(activeTagFilter)) {
      activeTagFilter = null;
    }

    // Apply tag filter
    let filtered = cachedNotes;
    if (activeTagFilter) {
      filtered = cachedNotes.filter((doc) =>
        (doc.tags || []).includes(activeTagFilter)
      );
    }

    // Client-side sort (server returns pinned-first, lastAccessed desc by default)
    const sorted = [...filtered].sort((a, b) => {
      if (a.pinned && !b.pinned) return -1;
      if (!a.pinned && b.pinned) return 1;

      switch (currentSort) {
        case "title":
          return a.title.localeCompare(b.title, "de");
        case "created":
          return new Date(b.created) - new Date(a.created);
        case "lastAccessed":
        default:
          return new Date(b.lastAccessed) - new Date(a.lastAccessed);
      }
    });

    notesGrid.innerHTML = "";

    if (sorted.length === 0) {
      notesGrid.style.display = "none";
      notesEmpty.style.display = "";
    } else {
      notesGrid.style.display = "";
      notesEmpty.style.display = "none";

      sorted.forEach((doc) => {
        const card = document.createElement("div");
        card.className = "note-card" + (doc.pinned ? " pinned" : "");
        card.dataset.id = doc.id;

        const tagsHtml = (doc.tags || []).length
          ? `<div class="note-card-tags">${(doc.tags || []).map((t) => `<span class="tag-chip" data-tag="${escapeHtml(t)}">${escapeHtml(t)}</span>`).join("")}</div>`
          : "";

        card.innerHTML = `
          <div class="note-card-header">
            <div class="note-card-title">${escapeHtml(doc.title)}</div>
            <div class="note-card-actions">
              <button class="note-card-btn pin-btn" title="${doc.pinned ? "Unpin" : "Pin"}" data-action="pin">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="${doc.pinned ? "currentColor" : "none"}" stroke="currentColor" stroke-width="1.5">
                  <path d="M4 2h8l-1 5 2 2v1H8.5L8 15l-.5-5H3V9l2-2z"/>
                </svg>
              </button>
              <button class="note-card-btn delete-btn" title="Löschen" data-action="delete">&times;</button>
            </div>
          </div>
          ${doc.preview ? `<div class="note-card-preview">${escapeHtml(doc.preview)}</div>` : ""}
          ${tagsHtml}
          <div class="note-card-meta">
            <span>&#128197; Erstellt: ${formatDate(doc.created)}</span>
            <span>&#128338; Zuletzt: ${formatDate(doc.lastAccessed)}</span>
          </div>
        `;

        // Click card to open
        card.addEventListener("click", (e) => {
          if (e.target.closest("[data-action]")) return;
          if (e.target.closest(".tag-chip")) return;
          openDocument(doc.id);
        });

        // Tag chips on card → set filter
        card.querySelectorAll(".tag-chip").forEach((chip) => {
          chip.addEventListener("click", (e) => {
            e.stopPropagation();
            activeTagFilter = activeTagFilter === chip.dataset.tag ? null : chip.dataset.tag;
            renderOverview();
          });
        });

        // Pin button
        card.querySelector("[data-action='pin']").addEventListener("click", async (e) => {
          e.stopPropagation();
          try {
            await apiPost("togglePin", { id: doc.id });
            renderOverview();
          } catch (err) {
            console.error("Failed to toggle pin:", err);
          }
        });

        // Delete button
        card.querySelector("[data-action='delete']").addEventListener("click", (e) => {
          e.stopPropagation();
          showDeleteModal(doc.id, doc.title);
        });

        notesGrid.appendChild(card);
      });
    }
  }

  function renderTagFilterBar(allTags) {
    tagFilterBar.innerHTML = "";
    if (allTags.size === 0) {
      tagFilterBar.style.display = "none";
      return;
    }
    tagFilterBar.style.display = "";

    const sortedTags = [...allTags].sort((a, b) => a.localeCompare(b, "de"));

    sortedTags.forEach((tag) => {
      const btn = document.createElement("button");
      btn.className = "tag-filter-chip" + (activeTagFilter === tag ? " active" : "");
      btn.textContent = tag;
      btn.addEventListener("click", () => {
        activeTagFilter = activeTagFilter === tag ? null : tag;
        renderOverview();
      });
      tagFilterBar.appendChild(btn);
    });
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  // ── Delete modal ──────────────────────────────────
  function showDeleteModal(id, title) {
    deleteTargetId = id;
    deleteModalText.textContent = `"${title}" wird unwiderruflich gelöscht.`;
    deleteModal.style.display = "";
  }

  function hideDeleteModal() {
    deleteTargetId = null;
    deleteModal.style.display = "none";
  }

  // ══════════════════════════════════════════════════
  //  Page navigation (overview ↔ editor)
  // ══════════════════════════════════════════════════

  async function showOverview() {
    if (currentDocId) {
      await saveCurrentDoc();
    }
    currentDocId = null;
    app.setAttribute("data-page", "overview");
    renderOverview();
  }

  async function openDocument(id) {
    try {
      const doc = await apiGet("get", { id });

      currentDocId = id;
      input.value = doc.content;

      app.setAttribute("data-page", "editor");

      updatePreview();
      updateLineNumbers();
      updateStats();
      markSaved();
      input.focus();
    } catch (err) {
      console.error("Failed to open document:", err);
    }
  }

  async function saveCurrentDoc() {
    if (!currentDocId) return;
    try {
      await apiPost("update", {
        id: currentDocId,
        content: input.value,
      });
      markSaved();
    } catch (err) {
      console.error("Failed to save:", err);
      saveStatus.textContent = "Save failed";
      saveStatus.classList.remove("saved");
    }
  }

  // ══════════════════════════════════════════════════
  //  Editor functionality
  // ══════════════════════════════════════════════════

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

    [toggleThemeBtn, toggleThemeEditorBtn].forEach((btn) => {
      if (!btn) return;
      const sunIcon = btn.querySelector(".icon-sun");
      const moonIcon = btn.querySelector(".icon-moon");
      if (sunIcon) sunIcon.style.display = isDark ? "none" : "";
      if (moonIcon) moonIcon.style.display = isDark ? "" : "none";
    });

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

  // ── Auto-save ─────────────────────────────────────
  function scheduleAutoSave() {
    clearTimeout(saveTimeout);
    saveStatus.textContent = "Unsaved";
    saveStatus.classList.remove("saved");
    saveTimeout = setTimeout(() => {
      saveCurrentDoc();
    }, 1200);
  }

  function markSaved() {
    saveStatus.textContent = "Saved";
    saveStatus.classList.add("saved");
  }

  // ── Markdown → HTML ───────────────────────────────
  function stripFrontMatter(text) {
    return text.replace(/^---\s*\n[\s\S]*?\n---\s*\n?/, "");
  }

  function updatePreview() {
    const raw = stripFrontMatter(input.value);
    const html = marked.parse(raw);
    preview.innerHTML = DOMPurify.sanitize(html);
    processTaskLists();
    updateToc();
    attachTaskCheckboxListeners();
  }

  // Enable task list checkboxes and add proper classes
  function processTaskLists() {
    // Find all list items with checkboxes (GFM task lists)
    const taskListItems = preview.querySelectorAll('li > input[type="checkbox"]');

    taskListItems.forEach((checkbox) => {
      const li = checkbox.parentElement;

      // Enable the checkbox (GFM renders them as disabled by default)
      checkbox.removeAttribute('disabled');
      checkbox.setAttribute('data-task', 'true');

      // Add task-list-item class to the li
      li.classList.add('task-list-item');

      // Add contains-task-list class to the parent ul/ol
      const list = li.parentElement;
      if (list && (list.tagName === 'UL' || list.tagName === 'OL')) {
        list.classList.add('contains-task-list');
      }
    });
  }

  // Handle checkbox clicks in task lists
  function attachTaskCheckboxListeners() {
    const checkboxes = preview.querySelectorAll('input[type="checkbox"][data-task="true"]');
    checkboxes.forEach((checkbox, index) => {
      checkbox.addEventListener('change', (e) => {
        e.preventDefault();
        toggleTaskInMarkdown(index, checkbox.checked);
      });
    });
  }

  function toggleTaskInMarkdown(taskIndex, isChecked) {
    const lines = input.value.split('\n');
    let currentTaskIndex = 0;

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      // Match task list items: - [ ] or - [x] or - [X]
      const taskMatch = line.match(/^(\s*[-*]\s+)\[([ xX])\](.*)$/);

      if (taskMatch) {
        if (currentTaskIndex === taskIndex) {
          // Toggle the checkbox state
          const prefix = taskMatch[1];
          const suffix = taskMatch[3];
          const newState = isChecked ? 'x' : ' ';
          lines[i] = `${prefix}[${newState}]${suffix}`;

          // Update the editor
          input.value = lines.join('\n');
          markDirty();
          updatePreview();
          break;
        }
        currentTaskIndex++;
      }
    }
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
      const available = workspaceWidth - tocWidth - 5;
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

      const files = e.dataTransfer.files;
      if (!files.length) return;

      const validExts = [".md", ".markdown", ".txt", ".text"];
      const validTypes = ["text/markdown", "text/plain", "text/x-markdown", ""];

      const isOnOverview = app.getAttribute("data-page") === "overview";

      Array.from(files).forEach((file) => {
        const ext = "." + file.name.split(".").pop().toLowerCase();
        if (validTypes.includes(file.type) || validExts.includes(ext)) {
          const reader = new FileReader();
          reader.onload = async (evt) => {
            if (isOnOverview || files.length > 1) {
              try {
                const doc = await apiPost("create", { content: evt.target.result });
                if (files.length === 1) {
                  openDocument(doc.id);
                } else {
                  renderOverview();
                }
              } catch (err) {
                console.error("Failed to import file:", err);
              }
            } else {
              input.value = evt.target.result;
              onInput();
              input.scrollTop = 0;
            }
          };
          reader.readAsText(file);
        }
      });
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
    const title = extractTitle(input.value).replace(/[^a-zA-Z0-9äöüÄÖÜß\s-]/g, "").trim() || "document";
    downloadFile(input.value, title + ".md", "text/markdown");
  }

  function downloadHtml() {
    const html = marked.parse(input.value);
    const sanitized = DOMPurify.sanitize(html);
    const title = extractTitle(input.value);
    const fullHtml = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${escapeHtml(title)}</title>
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
    downloadFile(fullHtml, (title || "document") + ".html", "text/html");
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
      input.setRangeText("  ", start, end, "end");
    } else {
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

    if (e.key === "Tab") {
      handleTab(e);
      e.stopPropagation();
      return;
    }

    if (mod && e.key === "b") {
      e.preventDefault();
      e.stopPropagation();
      toolbarActions.bold();
      return;
    }

    if (mod && e.key === "i") {
      e.preventDefault();
      e.stopPropagation();
      toolbarActions.italic();
      return;
    }

    if (mod && !e.shiftKey && e.key === "s") {
      e.preventDefault();
      e.stopPropagation();
      downloadMarkdown();
      return;
    }

    if (mod && e.shiftKey && e.key === "S") {
      e.preventDefault();
      e.stopPropagation();
      downloadHtml();
      return;
    }

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

  // ══════════════════════════════════════════════════
  //  Event binding
  // ══════════════════════════════════════════════════

  function bindAuthEvents() {
    loginForm.addEventListener("submit", handleLogin);
    registerForm.addEventListener("submit", handleRegister);

    showRegisterBtn.addEventListener("click", () => {
      loginForm.style.display = "none";
      registerForm.style.display = "";
      hideAuthError(loginError);
    });

    showLoginBtn.addEventListener("click", () => {
      registerForm.style.display = "none";
      loginForm.style.display = "";
      hideAuthError(registerError);
    });

    btnLogout.addEventListener("click", handleLogout);
  }

  let appEventsbound = false;

  function bindAppEvents() {
    if (appEventsbound) return;
    appEventsbound = true;

    // ── Overview events ─────────────────────────────
    btnNewNote.addEventListener("click", async () => {
      try {
        const doc = await apiPost("create", {});
        openDocument(doc.id);
      } catch (err) {
        console.error("Failed to create note:", err);
      }
    });

    btnNewNoteEmpty.addEventListener("click", async () => {
      try {
        const doc = await apiPost("create", {});
        openDocument(doc.id);
      } catch (err) {
        console.error("Failed to create note:", err);
      }
    });

    btnBackOverview.addEventListener("click", () => showOverview());

    // Debounced search (calls API with search query)
    searchInput.addEventListener("input", () => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        renderOverview();
      }, 300);
    });

    $$(".sort-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        currentSort = btn.dataset.sort;
        localStorage.setItem(SORT_KEY, currentSort);
        $$(".sort-btn").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        renderOverview();
      });
    });

    // ── Delete modal events ─────────────────────────
    deleteConfirmBtn.addEventListener("click", async () => {
      if (deleteTargetId) {
        try {
          await apiPost("delete", { id: deleteTargetId });
          hideDeleteModal();
          renderOverview();
        } catch (err) {
          console.error("Failed to delete:", err);
        }
      }
    });

    deleteCancelBtn.addEventListener("click", hideDeleteModal);

    deleteModal.addEventListener("click", (e) => {
      if (e.target === deleteModal) hideDeleteModal();
    });

    // ── Editor input events ─────────────────────────
    input.addEventListener("input", onInput);
    input.addEventListener("scroll", () => {
      syncLineNumberScroll();
      if (scrollSyncEnabled) syncEditorToPreview();
    });
    input.addEventListener("keydown", handleKeyDown);
    input.addEventListener("click", updateCursorPos);
    input.addEventListener("keyup", updateCursorPos);

    preview.addEventListener("scroll", () => {
      if (scrollSyncEnabled) syncPreviewToEditor();
    });

    // View mode buttons
    $$(".view-btn").forEach((btn) => {
      btn.addEventListener("click", () => setViewMode(btn.dataset.view));
    });

    // Toolbar buttons
    $$("[data-action]").forEach((btn) => {
      if (btn.closest(".toolbar")) {
        btn.addEventListener("click", () => {
          const action = toolbarActions[btn.dataset.action];
          if (action) action();
        });
      }
    });

    // Header actions
    copyBtn.addEventListener("click", copyMarkdown);
    downloadMdBtn.addEventListener("click", downloadMarkdown);
    downloadHtmlBtn.addEventListener("click", downloadHtml);

    // Theme toggles
    toggleThemeBtn.addEventListener("click", toggleTheme);
    toggleThemeEditorBtn.addEventListener("click", toggleTheme);

    // ToC toggle
    toggleTocBtn.addEventListener("click", () => {
      tocSidebar.classList.toggle("open");
    });
    closeTocBtn.addEventListener("click", () => {
      tocSidebar.classList.remove("open");
    });

    // Global keyboard shortcuts
    document.addEventListener("keydown", (e) => {
      const mod = e.ctrlKey || e.metaKey;
      if (app.getAttribute("data-page") === "editor") {
        if (mod && !e.shiftKey && e.key === "s") {
          e.preventDefault();
          downloadMarkdown();
        }
        if (mod && e.shiftKey && e.key === "S") {
          e.preventDefault();
          downloadHtml();
        }
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
      // Escape goes back to overview
      if (e.key === "Escape" && app.getAttribute("data-page") === "editor") {
        showOverview();
      }
    });

    // Gutter resize
    initGutterResize();

    // Drag & drop
    initDragDrop();
  }

  // ══════════════════════════════════════════════════
  //  Start app after login
  // ══════════════════════════════════════════════════

  function startApp() {
    loadViewMode();

    // Restore sort button state
    $$(".sort-btn").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.sort === currentSort);
    });

    // Start on overview
    app.setAttribute("data-page", "overview");
    renderOverview();

    bindAppEvents();
  }

  // ══════════════════════════════════════════════════
  //  Init
  // ══════════════════════════════════════════════════

  function init() {
    loadTheme();
    bindAuthEvents();
    checkSession();
  }

  init();
})();
