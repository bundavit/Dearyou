const audio = document.querySelector("[data-letter-audio]");
const audioToggle = document.querySelector("[data-audio-toggle]");

const updateAudioToggle = () => {
  if (!audio || !audioToggle) return;

  if (audio.paused) {
    audioToggle.classList.remove("is-playing");
    audioToggle.setAttribute("aria-label", "Play background music");
    audioToggle.innerHTML = '<i class="bi bi-play-fill"></i><span>Play music</span>';
    return;
  }

  audioToggle.classList.add("is-playing");
  audioToggle.setAttribute("aria-label", audio.muted ? "Unmute background music" : "Mute background music");
  audioToggle.innerHTML = audio.muted
    ? '<i class="bi bi-volume-mute-fill"></i><span>Unmute music</span>'
    : '<i class="bi bi-volume-up-fill"></i><span>Mute music</span>';
};

const startLetterAudio = async () => {
  if (!audio || !audio.paused) return;
  try {
    await audio.play();
  } catch {
    updateAudioToggle();
  }
};

const openLetter = () => {
  const stage = document.querySelector("#envelope-stage");
  const letter = document.querySelector("#letter-content");
  if (!stage || !letter) return;
  startLetterAudio();
  stage.classList.add("opening");
  document.querySelector("#open-letter")?.setAttribute("aria-expanded", "true");
  setTimeout(() => {
    stage.hidden = true;
    letter.hidden = false;
    letter.classList.add("revealed");
    letter.setAttribute("tabindex", "-1");
    letter.focus?.();
    document.querySelectorAll("[data-letter-video]").forEach(video => {
      if (video.getBoundingClientRect().top < window.innerHeight) video.play().catch(() => {});
    });
  }, matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 950);
};
document.querySelector("#open-letter")?.addEventListener("click", openLetter);
document.querySelector("#open-letter-text")?.addEventListener("click", openLetter);
document.querySelector("#close-letter")?.addEventListener("click", () => {
  const letter = document.querySelector("#letter-content");
  const stage = document.querySelector("#envelope-stage");
  if (!letter || !stage) return;

  letter.hidden = true;
  letter.classList.remove("revealed");
  audio?.pause();
  document.querySelectorAll("[data-letter-video]").forEach(video => video.pause());
  stage.hidden = false;
  stage.classList.remove("opening");
  document.querySelector("#open-letter")?.setAttribute("aria-expanded", "false");
  document.querySelector("#open-letter-text")?.focus();
});
document.querySelectorAll("[data-copy]").forEach(button => button.addEventListener("click", async () => {
  const input = document.querySelector(button.dataset.copy);
  await navigator.clipboard.writeText(input.value);
  button.textContent = "Copied";
}));

document.querySelectorAll("[data-auto-dismiss-alert]").forEach(alert => {
  setTimeout(() => {
    alert.classList.add("is-dismissing");
    alert.addEventListener("transitionend", () => alert.remove(), { once: true });
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

document.querySelectorAll("[data-password-toggle]").forEach(button => {
  button.addEventListener("click", () => {
    const field = button.closest(".password-field");
    const input = field?.querySelector("[data-password-input]");
    if (!input) return;

    const reveal = input.type === "password";
    input.type = reveal ? "text" : "password";
    button.setAttribute("aria-pressed", String(reveal));
    button.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
    button.innerHTML = reveal ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    input.focus();
  });
});

document.querySelectorAll("[data-user-navbar]").forEach(navbar => {
  const panels = Array.from(navbar.querySelectorAll("[data-navbar-panel]"));

  panels.forEach(panel => {
    panel.addEventListener("toggle", () => {
      if (!panel.open) return;
      panels.forEach(other => {
        if (other !== panel) other.open = false;
      });
    });
  });

  document.addEventListener("click", event => {
    if (!navbar.contains(event.target)) panels.forEach(panel => panel.open = false);
  });

  document.addEventListener("keydown", event => {
    if (event.key !== "Escape") return;
    panels.forEach(panel => panel.open = false);
  });
});

const platformSettingsForm = document.querySelector("[data-platform-settings-form]");
if (platformSettingsForm) {
  const expiryChoices = Array.from(platformSettingsForm.querySelectorAll("[data-expiry-choice]"));
  const defaultExpiry = platformSettingsForm.querySelector("[data-default-expiry]");
  const customExpiry = platformSettingsForm.querySelector("[data-custom-expiry]");
  const customExpiryValue = platformSettingsForm.querySelector("[data-custom-expiry-value]");
  const customExpiryUnit = platformSettingsForm.querySelector("[data-custom-expiry-unit]");
  const customExpiryAdd = platformSettingsForm.querySelector("[data-add-custom-expiry]");
  const customExpiryList = platformSettingsForm.querySelector("[data-custom-expiry-list]");

  const durationLabel = minutes => {
    if (minutes === 1) return "1 minute";
    if (minutes < 60) return `${minutes} minutes`;
    if (minutes % 1440 === 0) {
      const days = minutes / 1440;
      return days === 1 ? "1 day" : `${days} days`;
    }
    if (minutes % 60 === 0) {
      const hours = minutes / 60;
      return hours === 1 ? "1 hour" : `${hours} hours`;
    }
    return `${minutes} minutes`;
  };

  const customMinutes = () => (customExpiry?.value || "")
    .split(",")
    .map(value => Number.parseInt(value.trim(), 10))
    .filter(value => Number.isInteger(value) && value >= 1 && value <= 43200);

  const setCustomMinutes = minutes => {
    if (!customExpiry) return;
    customExpiry.value = [...new Set(minutes)]
      .sort((left, right) => left - right)
      .join(", ");
  };

  const renderCustomMinutes = () => {
    if (!customExpiryList) return;

    const values = customMinutes();
    customExpiryList.innerHTML = "";
    customExpiryList.hidden = values.length === 0;

    values.forEach(minutes => {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = "custom-expiry-chip";
      chip.dataset.minutes = String(minutes);
      chip.innerHTML = `<span>${durationLabel(minutes)}</span><i class="bi bi-x-lg" aria-hidden="true"></i>`;
      chip.setAttribute("aria-label", `Remove ${durationLabel(minutes)}`);
      customExpiryList.append(chip);
    });
  };

  const syncDefaultExpiry = () => {
    if (!defaultExpiry) return;

    const presetValues = expiryChoices
      .filter(choice => choice.checked)
      .map(choice => choice.value);
    const customValues = customMinutes()
      .map(String);
    const enabledValues = [...new Set([...presetValues, ...customValues])]
      .sort((left, right) => Number(left) - Number(right));

    Array.from(defaultExpiry.options).forEach(option => {
      if (!enabledValues.includes(option.value)) option.remove();
    });

    enabledValues.forEach(value => {
      if (Array.from(defaultExpiry.options).some(option => option.value === value)) return;
      defaultExpiry.add(new Option(durationLabel(Number(value)), value));
    });

    Array.from(defaultExpiry.options)
      .sort((left, right) => Number(left.value) - Number(right.value))
      .forEach(option => defaultExpiry.add(option));

    if (enabledValues.length && !enabledValues.includes(defaultExpiry.value)) {
      defaultExpiry.value = enabledValues[0];
    }
  };

  customExpiryAdd?.addEventListener("click", () => {
    const amount = Number.parseInt(customExpiryValue?.value || "", 10);
    const unit = customExpiryUnit?.value || "minutes";
    const multiplier = unit === "days" ? 1440 : unit === "hours" ? 60 : 1;
    const minutes = amount * multiplier;

    if (!Number.isInteger(minutes) || minutes < 1 || minutes > 43200) {
      customExpiryValue?.focus();
      return;
    }

    setCustomMinutes([...customMinutes(), minutes]);
    if (customExpiryValue) customExpiryValue.value = "";
    renderCustomMinutes();
    syncDefaultExpiry();
  });

  customExpiryList?.addEventListener("click", event => {
    const chip = event.target.closest("[data-minutes]");
    if (!chip) return;

    const minutes = Number.parseInt(chip.dataset.minutes || "", 10);
    setCustomMinutes(customMinutes().filter(value => value !== minutes));
    renderCustomMinutes();
    syncDefaultExpiry();
  });

  expiryChoices.forEach(choice => choice.addEventListener("change", syncDefaultExpiry));
  customExpiry?.addEventListener("input", () => {
    renderCustomMinutes();
    syncDefaultExpiry();
  });
  renderCustomMinutes();
  syncDefaultExpiry();
}

document.querySelectorAll("[data-remove-image-button]").forEach(button => {
  button.addEventListener("click", () => {
    const preview = button.closest("[data-removable-image]");
    const input = preview?.querySelector("[data-remove-image-input]");
    const label = button.querySelector("span");
    const icon = button.querySelector("i");
    const note = preview?.querySelector("[data-remove-image-note]");
    if (!preview || !input || !label || !icon || !note) return;

    input.checked = !input.checked;
    preview.classList.toggle("is-pending-removal", input.checked);
    button.classList.toggle("btn-outline-danger", !input.checked);
    button.classList.toggle("btn-outline-secondary", input.checked);
    icon.className = input.checked ? "bi bi-arrow-counterclockwise" : "bi bi-trash";
    label.textContent = input.checked ? "Undo delete" : "Delete picture";
    note.textContent = input.checked
      ? "This picture will be deleted when you save the letter."
      : "The picture will be deleted when you save the letter.";
  });
});

document.querySelectorAll("[data-image-upload]").forEach(input => input.addEventListener("change", () => {
  const files = Array.from(input.files || []);
  const maxSizeMb = Number(input.dataset.maxSizeMb || 10);
  const maxFiles = Number(input.dataset.maxFiles || 0);
  const oldError = input.parentElement.querySelector("[data-upload-error]");
  oldError?.remove();
  input.classList.remove("is-invalid");
  const oversized = files.find(file => file.size > maxSizeMb * 1024 * 1024);
  const tooManyFiles = maxFiles > 0 && files.length > maxFiles;
  if (!oversized && !tooManyFiles) return;

  input.value = "";
  input.classList.add("is-invalid");
  const error = document.createElement("div");
  error.className = "invalid-feedback d-block";
  error.dataset.uploadError = "";
  error.textContent = tooManyFiles
    ? `Choose no more than ${maxFiles} files at once.`
    : `${oversized.name} is larger than ${maxSizeMb} MB. Please choose a smaller media file.`;
  input.insertAdjacentElement("afterend", error);
}));
document.querySelector("[data-audio-upload]")?.addEventListener("change", event => {
  const input = event.currentTarget;
  const file = input.files?.[0];
  const maxSizeMb = Number(input.dataset.maxSizeMb || 12);
  input.parentElement.querySelector("[data-upload-error]")?.remove();
  input.classList.remove("is-invalid");
  if (!file || file.size <= maxSizeMb * 1024 * 1024) return;
  input.value = "";
  input.classList.add("is-invalid");
  const error = document.createElement("div");
  error.className = "invalid-feedback d-block";
  error.dataset.uploadError = "";
  error.textContent = `${file.name} is larger than ${maxSizeMb} MB. Please choose a smaller audio file.`;
  input.insertAdjacentElement("afterend", error);
});

const managedVideos = Array.from(document.querySelectorAll("[data-autoplay-when-visible]"));
if (managedVideos.length && "IntersectionObserver" in window) {
  const videoObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      const video = entry.target;
      const letter = video.closest("#letter-content");
      const letterIsClosed = letter?.hidden;

      if (entry.isIntersecting && !document.hidden && !letterIsClosed) {
        video.play().catch(() => {});
      } else {
        video.pause();
      }
    });
  }, { rootMargin: "120px 0px", threshold: 0.08 });

  managedVideos.forEach(video => videoObserver.observe(video));
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) managedVideos.forEach(video => video.pause());
  });
}

const chapterPreview = document.querySelector("[data-chapter-preview]");
const updateChapterPreview = () => {
  if (!chapterPreview) return;

  const sender = document.querySelector("[data-chapter-sender-name]")?.value.trim() || "Anonymous";
  const recipient = document.querySelector("[data-chapter-recipient-name]")?.value.trim() || "Someone special";
  const dateValue = document.querySelector("[data-chapter-date]")?.value;
  const accent = document.querySelector("[data-chapter-color]")?.value;
  const senderLabel = chapterPreview.querySelector("[data-chapter-sender-label]");
  const recipientLabel = chapterPreview.querySelector("[data-chapter-recipient-label]");
  const senderInitial = chapterPreview.querySelector("[data-chapter-sender-initial]");
  const recipientInitial = chapterPreview.querySelector("[data-chapter-recipient-initial]");
  const dateLabel = chapterPreview.querySelector("[data-chapter-date-label]");

  if (senderLabel) senderLabel.textContent = sender;
  if (recipientLabel) recipientLabel.textContent = recipient;
  if (senderInitial) senderInitial.textContent = sender.charAt(0).toUpperCase();
  if (recipientInitial) recipientInitial.textContent = recipient.charAt(0).toUpperCase();
  if (accent) chapterPreview.style.setProperty("--chapter-accent", accent);
  if (dateLabel) {
    dateLabel.textContent = dateValue
      ? `Started from ${new Date(`${dateValue}T00:00:00`).toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" })}`
      : "Add a start date to show it here";
  }
};

const previewChapterImage = (input, targetSelector) => {
  const file = input.files?.[0];
  const target = chapterPreview?.querySelector(targetSelector);
  if (!file || !target) return;

  const reader = new FileReader();
  reader.addEventListener("load", () => {
    target.innerHTML = "";
    const image = document.createElement("img");
    image.src = reader.result;
    image.alt = "";
    target.append(image);
  });
  reader.readAsDataURL(file);
};

document.querySelector("[data-chapter-sender-name]")?.addEventListener("input", updateChapterPreview);
document.querySelector("[data-chapter-recipient-name]")?.addEventListener("input", updateChapterPreview);
document.querySelector("[data-chapter-date]")?.addEventListener("change", updateChapterPreview);
document.querySelector("[data-chapter-color]")?.addEventListener("input", updateChapterPreview);
document.querySelector("[data-chapter-heading]")?.addEventListener("input", event => {
  const heading = chapterPreview?.querySelector("[data-chapter-heading-preview]");
  if (heading) heading.textContent = event.currentTarget.value.trim() || "A beautiful new chapter begins.";
});
document.querySelector("[data-chapter-sender-image]")?.addEventListener("change", event => previewChapterImage(event.currentTarget, "[data-chapter-sender-avatar]"));
document.querySelector("[data-chapter-recipient-image]")?.addEventListener("change", event => previewChapterImage(event.currentTarget, "[data-chapter-recipient-avatar]"));
updateChapterPreview();

const presets = {
  confession: { theme: "romantic", font_style: "handwritten", envelope_style: "classic", decoration_type: "hearts", primary_color: "#d85b78", secondary_color: "#fff1e8", response_mode: "buttons_with_message", question_text: "Do you want to give us a chance?", positive_button_text: "Yes, I do", negative_button_text: "Not right now" },
  apology: { theme: "peaceful", font_style: "elegant", envelope_style: "vintage", decoration_type: "flowers", primary_color: "#779b8c", secondary_color: "#f5f1e8", response_mode: "buttons_with_message", question_text: "Can you forgive me?", positive_button_text: "I forgive you", negative_button_text: "Not yet" },
  birthday: { theme: "celebration", font_style: "friendly", envelope_style: "gift", decoration_type: "balloons", primary_color: "#7b68c7", secondary_color: "#fff6cf", response_mode: "message", question_text: "Want to leave a little birthday reply?", positive_button_text: "Thank you", negative_button_text: "Send a note" },
  anniversary: { theme: "romantic", font_style: "elegant", envelope_style: "rounded", decoration_type: "sparkles", primary_color: "#a64f67", secondary_color: "#fff4ed", response_mode: "message", question_text: "What is your favorite memory of us?", positive_button_text: "Send a memory", negative_button_text: "Maybe later" },
  valentine: { theme: "romantic", font_style: "handwritten", envelope_style: "rounded", decoration_type: "hearts", primary_color: "#cc3158", secondary_color: "#fff0f3", response_mode: "buttons_with_message", question_text: "Will you be my Valentine?", positive_button_text: "Yes", negative_button_text: "Let's talk" },
  congratulations: { theme: "celebration", font_style: "modern", envelope_style: "gift", decoration_type: "confetti", primary_color: "#d48b24", secondary_color: "#fff8dc", response_mode: "message", question_text: "How are you feeling about this moment?", positive_button_text: "Amazing", negative_button_text: "Send a note" },
  "thank-you": { theme: "warm", font_style: "classic", envelope_style: "vintage", decoration_type: "flowers", primary_color: "#b36b45", secondary_color: "#fff8ed", response_mode: "message", question_text: "Would you like to leave a reply?", positive_button_text: "Reply", negative_button_text: "Maybe later" },
  friendship: { theme: "friendship", font_style: "friendly", envelope_style: "airmail", decoration_type: "stars", primary_color: "#398c9b", secondary_color: "#eefbfa", response_mode: "message", question_text: "Want to send a note back?", positive_button_text: "Reply", negative_button_text: "Maybe later" },
  graduation: { theme: "celebration", font_style: "formal", envelope_style: "airmail", decoration_type: "stars", primary_color: "#3d5b99", secondary_color: "#f4f1df", response_mode: "message", question_text: "What are you looking forward to next?", positive_button_text: "Share it", negative_button_text: "Maybe later" },
  celebration: { theme: "celebration", font_style: "modern", envelope_style: "gift", decoration_type: "confetti", primary_color: "#8b63c7", secondary_color: "#fff7d9", response_mode: "message", question_text: "Want to leave a celebration note?", positive_button_text: "Celebrate", negative_button_text: "Maybe later" },
  custom: { theme: "warm", font_style: "classic", envelope_style: "classic", decoration_type: "sparkles", primary_color: "#d85b78", secondary_color: "#fff1e8", response_mode: "message", question_text: "Would you like to reply?", positive_button_text: "Reply", negative_button_text: "Maybe later" }
};

document.querySelector("#apply-preset")?.addEventListener("click", () => {
  const form = document.querySelector("#apply-preset").closest("form");
  const preset = presets[form.elements.category.value];
  if (!preset) return;
  Object.entries(preset).forEach(([name, value]) => {
    if (form.elements[name]) form.elements[name].value = value;
  });
  updateFontPreview();
});

document.querySelectorAll("[data-response-choice]").forEach(button => button.addEventListener("click", () => {
  const form = button.closest("[data-response-form]");
  const compose = form.querySelector(".response-compose");
  if (!compose) return;
  compose.hidden = false;
  compose.querySelector("[data-response-value]").value = button.value;
  compose.querySelector(".response-guidance").textContent = button.value === "negative"
    ? "That is completely okay. You can explain only if you want to."
    : "You can add a private note, or simply send your answer.";
  compose.querySelector("textarea").focus();
}));

document.querySelector("[data-async-response]")?.addEventListener("submit", async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitter = event.submitter;
  const formData = new FormData(form);
  if (submitter?.name) formData.set(submitter.name, submitter.value);

  form.classList.add("is-submitting");
  form.querySelectorAll("button,textarea").forEach(control => control.disabled = true);

  try {
    const response = await fetch(form.action, {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": formData.get("_token") },
      body: formData
    });
    if (!response.ok) throw new Error("Response could not be sent.");

    const payload = await response.json();
    const wrapper = document.createElement("div");
    wrapper.innerHTML = payload.html;
    const result = wrapper.firstElementChild;
    form.replaceWith(result);
    requestAnimationFrame(() => result?.classList.add("is-visible"));
    result?.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "center" });
  } catch (error) {
    form.classList.remove("is-submitting");
    form.querySelectorAll("button,textarea").forEach(control => control.disabled = false);
    let notice = form.querySelector("[data-response-error]");
    if (!notice) {
      notice = document.createElement("p");
      notice.dataset.responseError = "";
      notice.className = "response-error";
      form.append(notice);
    }
    notice.textContent = error.message;
  }
});

audioToggle?.addEventListener("click", async () => {
  if (!audio) return;
  if (audio.paused) {
    audio.muted = false;
    await startLetterAudio();
  } else {
    audio.muted = !audio.muted;
  }
  updateAudioToggle();
});
audio?.addEventListener("play", updateAudioToggle);
audio?.addEventListener("pause", updateAudioToggle);
audio?.addEventListener("volumechange", updateAudioToggle);
updateAudioToggle();

const lightbox = document.querySelector("[data-memory-lightbox]");
const lightboxItems = Array.from(document.querySelectorAll("[data-lightbox-image]"));
let lightboxIndex = 0;
const renderLightbox = () => {
  const item = lightboxItems[lightboxIndex];
  const image = lightbox?.querySelector("[data-lightbox-main]");
  const video = lightbox?.querySelector("[data-lightbox-video]");
  const caption = lightbox?.querySelector("[data-lightbox-caption]");
  if (!item || !image || !video || !caption) return;

  const isVideo = item.dataset.lightboxType === "video";
  const alt = item.dataset.lightboxAlt || "";
  image.hidden = isVideo;
  video.hidden = !isVideo;
  video.pause();

  if (isVideo) {
    image.removeAttribute("src");
    video.src = item.dataset.lightboxImage;
    video.play().catch(() => {});
  } else {
    video.removeAttribute("src");
    video.load();
    image.src = item.dataset.lightboxImage;
    image.alt = alt;
  }
  caption.textContent = `${alt} (${lightboxIndex + 1} of ${lightboxItems.length})`;
};
lightboxItems.forEach((item, index) => item.addEventListener("click", () => {
  lightboxIndex = index;
  renderLightbox();
  lightbox?.showModal();
}));
document.querySelector("[data-lightbox-close]")?.addEventListener("click", () => lightbox?.close());
document.querySelector("[data-lightbox-prev]")?.addEventListener("click", () => {
  lightboxIndex = (lightboxIndex - 1 + lightboxItems.length) % lightboxItems.length;
  renderLightbox();
});
document.querySelector("[data-lightbox-next]")?.addEventListener("click", () => {
  lightboxIndex = (lightboxIndex + 1) % lightboxItems.length;
  renderLightbox();
});
lightbox?.addEventListener("click", event => {
  if (event.target === lightbox) lightbox.close();
});
lightbox?.addEventListener("close", () => lightbox.querySelector("[data-lightbox-video]")?.pause());
lightbox?.addEventListener("keydown", event => {
  if (event.key === "ArrowLeft") document.querySelector("[data-lightbox-prev]")?.click();
  if (event.key === "ArrowRight") document.querySelector("[data-lightbox-next]")?.click();
});

document.querySelector("[data-select-all]")?.addEventListener("change", event => {
  document.querySelectorAll('input[name="response_ids[]"]').forEach(checkbox => {
    checkbox.checked = event.target.checked;
  });
});

document.querySelectorAll("[data-auto-filter]").forEach(form => {
  const submit = () => {
    form.setAttribute("aria-busy", "true");
    HTMLFormElement.prototype.submit.call(form);
  };

  form.querySelector(".auto-filter-submit")?.setAttribute("hidden", "");
  form.querySelectorAll("[data-auto-filter-change]").forEach(field => {
    field.addEventListener("change", submit);
  });

  const search = form.querySelector("[data-auto-filter-search]");
  if (search) {
    let timer;
    let previousValue = search.value;
    search.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (search.value !== previousValue) submit();
      }, 450);
    });
  }
});

const toggleConfessionOptions = () => {
  const category = document.querySelector("#category");
  const options = document.querySelector("[data-confession-options]");
  if (category && options) options.hidden = category.value !== "confession";
};
document.querySelector("#category")?.addEventListener("change", toggleConfessionOptions);
toggleConfessionOptions();

const toggleAnniversaryOptions = () => {
  const category = document.querySelector("#category");
  const options = document.querySelector("[data-anniversary-options]");
  if (category && options) options.hidden = category.value !== "anniversary";
};
document.querySelector("#category")?.addEventListener("change", toggleAnniversaryOptions);
toggleAnniversaryOptions();

const updateFontPreview = () => {
  const select = document.querySelector("[data-font-select]");
  if (!select) return;

  const option = select.options[select.selectedIndex];
  const fontStack = option?.dataset.fontStack;
  if (fontStack) select.style.fontFamily = fontStack;
};
document.querySelector("[data-font-select]")?.addEventListener("change", updateFontPreview);
updateFontPreview();

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const enableDragOrder = (container, itemSelector, idAttribute) => {
  if (!container) return;
  let dragging;

  container.querySelectorAll(itemSelector).forEach(item => {
    item.addEventListener("dragstart", event => {
      event.stopPropagation();
      if (event.target.closest("input,textarea,select")) {
        event.preventDefault();
        return;
      }
      dragging = item;
      item.classList.add("is-dragging");
      event.dataTransfer.effectAllowed = "move";
    });
    item.addEventListener("dragend", async () => {
      item.classList.remove("is-dragging");
      dragging = null;
      const order = Array.from(container.querySelectorAll(itemSelector)).map(entry => Number(entry.dataset[idAttribute]));
      try {
        const response = await fetch(container.dataset.reorderUrl, {
          method: "PATCH",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
          body: JSON.stringify({ order })
        });
        if (!response.ok) throw new Error();
        container.classList.add("order-saved");
        setTimeout(() => container.classList.remove("order-saved"), 900);
      } catch {
        container.classList.add("order-error");
      }
    });
  });

  container.addEventListener("dragover", event => {
    event.preventDefault();
    event.stopPropagation();
    if (!dragging) return;
    const candidates = Array.from(container.querySelectorAll(`${itemSelector}:not(.is-dragging)`));
    const next = candidates.find(candidate => {
      const box = candidate.getBoundingClientRect();
      return event.clientY < box.top + box.height / 2;
    });
    container.insertBefore(dragging, next || null);
  });
};
enableDragOrder(document.querySelector("[data-sortable-memories]"), "[data-memory-id]", "memoryId");
document.querySelectorAll("[data-sortable-images]").forEach(container => enableDragOrder(container, "[data-image-id]", "imageId"));

if (matchMedia("(max-width: 767px)").matches) {
  document.querySelectorAll(".editor-section").forEach(section => section.removeAttribute("open"));
  document.querySelector(".editor-section")?.setAttribute("open", "");
}

document.querySelectorAll("form").forEach(form => form.addEventListener("submit", event => {
  if (form.matches("[data-async-response]")) return;
  const submitter = event.submitter;
  if (!submitter || submitter.dataset.keepEnabled !== undefined) return;
  if (submitter.name) {
    const submittedValue = document.createElement("input");
    submittedValue.type = "hidden";
    submittedValue.name = submitter.name;
    submittedValue.value = submitter.value;
    form.append(submittedValue);
  }
  submitter.disabled = true;
  if (submitter.tagName === "BUTTON") {
    submitter.dataset.originalText = submitter.textContent;
    submitter.textContent = "Working...";
  }
}));

const formatCountdown = milliseconds => {
  if (milliseconds <= 0) return "Expired";

  const totalSeconds = Math.floor(milliseconds / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) return `${hours}h ${minutes}m ${seconds}s`;
  return `${minutes}m ${seconds}s`;
};

const countdowns = document.querySelectorAll("[data-link-countdown]");
const updateLinkCountdowns = () => countdowns.forEach(countdown => {
  const expiresAt = Date.parse(countdown.dataset.linkCountdown);
  if (!Number.isNaN(expiresAt)) countdown.textContent = formatCountdown(expiresAt - Date.now());
});

if (countdowns.length) {
  updateLinkCountdowns();
  setInterval(updateLinkCountdowns, 1000);
}
