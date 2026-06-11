const openLetter = () => {
  const stage = document.querySelector("#envelope-stage");
  const letter = document.querySelector("#letter-content");
  if (!stage || !letter) return;
  stage.classList.add("opening");
  document.querySelector("#open-letter")?.setAttribute("aria-expanded", "true");
  setTimeout(() => {
    stage.hidden = true;
    letter.hidden = false;
    letter.classList.add("revealed");
    letter.setAttribute("tabindex", "-1");
    letter.focus?.();
  }, matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : 950);
};
document.querySelector("#open-letter")?.addEventListener("click", openLetter);
document.querySelector("#open-letter-text")?.addEventListener("click", openLetter);
document.querySelector("#reread")?.addEventListener("click", () => {
  document.querySelector("#letter-content").hidden = true;
  const stage = document.querySelector("#envelope-stage");
  stage.hidden = false; stage.classList.remove("opening");
  document.querySelector("#open-letter")?.setAttribute("aria-expanded", "false");
});
document.querySelectorAll("[data-copy]").forEach(button => button.addEventListener("click", async () => {
  const input = document.querySelector(button.dataset.copy);
  await navigator.clipboard.writeText(input.value);
  button.textContent = "Copied";
}));

document.querySelectorAll("[data-image-upload]").forEach(input => input.addEventListener("change", () => {
  const file = input.files?.[0];
  const oldError = input.parentElement.querySelector("[data-upload-error]");
  oldError?.remove();
  input.classList.remove("is-invalid");
  if (!file || file.size <= 5 * 1024 * 1024) return;

  input.value = "";
  input.classList.add("is-invalid");
  const error = document.createElement("div");
  error.className = "invalid-feedback d-block";
  error.dataset.uploadError = "";
  error.textContent = "This picture is larger than 5 MB. Please choose a smaller image.";
  input.insertAdjacentElement("afterend", error);
}));

const presets = {
  confession: { theme: "romantic", decoration_type: "hearts", primary_color: "#d85b78", secondary_color: "#fff1e8", response_mode: "buttons_with_message", question_text: "Do you want to give us a chance?", positive_button_text: "Yes, I do", negative_button_text: "Not right now" },
  apology: { theme: "peaceful", decoration_type: "flowers", primary_color: "#779b8c", secondary_color: "#f5f1e8", response_mode: "buttons_with_message", question_text: "Can you forgive me?", positive_button_text: "I forgive you", negative_button_text: "Not yet" },
  birthday: { theme: "celebration", decoration_type: "balloons", primary_color: "#7b68c7", secondary_color: "#fff6cf", response_mode: "message", question_text: "Want to leave a little birthday reply?", positive_button_text: "Thank you", negative_button_text: "Send a note" },
  anniversary: { theme: "romantic", decoration_type: "sparkles", primary_color: "#a64f67", secondary_color: "#fff4ed", response_mode: "message", question_text: "What is your favorite memory of us?", positive_button_text: "Send a memory", negative_button_text: "Maybe later" },
  valentine: { theme: "romantic", decoration_type: "hearts", primary_color: "#cc3158", secondary_color: "#fff0f3", response_mode: "buttons_with_message", question_text: "Will you be my Valentine?", positive_button_text: "Yes", negative_button_text: "Let's talk" },
  congratulations: { theme: "celebration", decoration_type: "confetti", primary_color: "#d48b24", secondary_color: "#fff8dc", response_mode: "message", question_text: "How are you feeling about this moment?", positive_button_text: "Amazing", negative_button_text: "Send a note" },
  "thank-you": { theme: "warm", decoration_type: "flowers", primary_color: "#b36b45", secondary_color: "#fff8ed", response_mode: "message", question_text: "Would you like to leave a reply?", positive_button_text: "Reply", negative_button_text: "Maybe later" },
  friendship: { theme: "friendship", decoration_type: "stars", primary_color: "#398c9b", secondary_color: "#eefbfa", response_mode: "message", question_text: "Want to send a note back?", positive_button_text: "Reply", negative_button_text: "Maybe later" },
  graduation: { theme: "celebration", decoration_type: "stars", primary_color: "#3d5b99", secondary_color: "#f4f1df", response_mode: "message", question_text: "What are you looking forward to next?", positive_button_text: "Share it", negative_button_text: "Maybe later" },
  celebration: { theme: "celebration", decoration_type: "confetti", primary_color: "#8b63c7", secondary_color: "#fff7d9", response_mode: "message", question_text: "Want to leave a celebration note?", positive_button_text: "Celebrate", negative_button_text: "Maybe later" },
  custom: { theme: "warm", decoration_type: "sparkles", primary_color: "#d85b78", secondary_color: "#fff1e8", response_mode: "message", question_text: "Would you like to reply?", positive_button_text: "Reply", negative_button_text: "Maybe later" }
};

document.querySelector("#apply-preset")?.addEventListener("click", () => {
  const form = document.querySelector("#apply-preset").closest("form");
  const preset = presets[form.elements.category.value];
  if (!preset) return;
  Object.entries(preset).forEach(([name, value]) => {
    if (form.elements[name]) form.elements[name].value = value;
  });
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

document.querySelectorAll("form").forEach(form => form.addEventListener("submit", event => {
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
