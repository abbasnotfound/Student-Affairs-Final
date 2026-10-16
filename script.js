"use strict";

// =============================================================================
// Student Affairs - Frontend Client Controller
// Connects UI forms to PHP/MySQL backend API endpoints
// =============================================================================

// Display messages safely without injecting unescaped HTML from user input
function showMessage(element, message, type = "info") {
  element.replaceChildren();

  const p = document.createElement("p");
  p.textContent = message;
  p.className = `msg-${type}`;

  if (type === "error") {
    p.style.color = "#b91c1c";
    p.style.fontWeight = "600";
  } else if (type === "success") {
    p.style.color = "#15803d";
    p.style.fontWeight = "600";
  } else {
    p.style.color = "#2459d3";
  }

  element.appendChild(p);
  element.dataset.type = type;
}

// Display key-value complaint tracking details safely
function showDetails(element, details) {
  element.replaceChildren();

  const card = document.createElement("div");
  card.style.background = "#f8fafc";
  card.style.border = "1px solid #e2e8f0";
  card.style.borderRadius = "10px";
  card.style.padding = "16px 18px";
  card.style.marginTop = "14px";

  details.forEach(([label, value, isHighlight]) => {
    const line = document.createElement("p");
    line.style.margin = "6px 0";
    line.style.fontSize = "13px";

    const heading = document.createElement("strong");
    heading.textContent = label + ": ";
    heading.style.color = "#10264a";

    const span = document.createElement("span");
    span.textContent = String(value ?? "—");

    if (isHighlight) {
      span.style.fontWeight = "750";

      if (String(value).toLowerCase() === "resolved") {
        span.style.color = "#14845b";
      } else if (String(value).toLowerCase() === "in progress") {
        span.style.color = "#2459d3";
      } else if (String(value).toLowerCase() === "rejected") {
        span.style.color = "#b91c1c";
      } else {
        span.style.color = "#b45309";
      }
    }

    line.append(heading, span);
    card.appendChild(line);
  });

  element.appendChild(card);
}

// =============================================================================
// WING + CLASS: show only sections that match the selected wing and class
// =============================================================================

const wingSelect = document.querySelector('select[name="wing"]');
const classSelect = document.querySelector('select[name="studentId"]');
const sectionField = document.querySelector("#section-field");

if (wingSelect && classSelect && sectionField) {
  const originalSectionInput = sectionField.querySelector('input[name="section"]');

  const sectionsByWingAndClass = {
    "Girls Wing": {
      "9th": [
        "Unity",
        "Faith",
        "Discipline"
      ],

      "10th": [
        "Wisdom",
        "Courage",
        "Excellence"
      ],

      "11th": [
        "Amina",
        "Ayesha",
        "Fatima",
        "Hajra"
      ],

      "12th": [
        "Hafsa",
        "Khadija",
        "Maryam",
        "Zainab"
      ]
    },

    "Boys Wing": {
      "9th": [
        "Shaheen",
        "Scholars",
        "Explorers",
        "Guardians"
      ],

      "10th": [
        "Leaders",
        "Dedicated",
        "Challenger",
        "Champion"
      ],

      "11th": [
        "Rafiqui",
        "Faiz Ahmad Faiz",
        "Aziz Bhatti",
        "Rashid Minhas",
        "Kernal Sher Khan"
      ],

      "12th": [
        "Bahoo",
        "Ghazi-Ilm-Din",
        "Sir Syed",
        "Iqbal",
        "Jinnah"
      ]
    }
  };

  function updateSectionField() {
    const sections =
      (sectionsByWingAndClass[wingSelect.value] || {})[classSelect.value] || [];

    let sectionSelect =
      sectionField.querySelector('select[name="section"]');

    // Keep Section as a dropdown for both wings
    // so the available options are controlled.
    if (!sectionSelect) {
      sectionSelect = document.createElement("select");

      sectionSelect.name = "section";
      sectionSelect.required = true;
      sectionSelect.setAttribute("aria-label", "Section");

      const currentInput =
        sectionField.querySelector('input[name="section"]');

      if (currentInput) {
        currentInput.replaceWith(sectionSelect);
      }
    }

    sectionSelect.replaceChildren();

    const placeholder = document.createElement("option");

    placeholder.value = "";
    placeholder.disabled = true;
    placeholder.selected = true;

    placeholder.textContent =
      !wingSelect.value
        ? "Select a wing first"
        : !classSelect.value
          ? "Select a class first"
          : "Select a section";

    sectionSelect.appendChild(placeholder);

    sections.forEach((section) => {
      const option = document.createElement("option");

      option.value = section;
      option.textContent = section;

      sectionSelect.appendChild(option);
    });

    sectionSelect.disabled = sections.length === 0;
  }

  wingSelect.addEventListener("change", updateSectionField);
  classSelect.addEventListener("change", updateSectionField);

  updateSectionField();
}

// =============================================================================
// 1. SUBMIT COMPLAINT FORM
// =============================================================================

const complaintForm = document.querySelector("#complaint-form");
const formMessage = document.querySelector("#form-message");

if (complaintForm && formMessage) {
  complaintForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!complaintForm.reportValidity()) {
      return;
    }

    const submitButton =
      complaintForm.querySelector('button[type="submit"]');

    const formData = new FormData(complaintForm);

    showMessage(
      formMessage,
      "Submitting your complaint to Student Affairs...",
      "info"
    );

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Submitting...";
    }

    try {
      const response = await fetch("api/submit.php", {
        method: "POST",
        body: formData,
        headers: {
          "Accept": "application/json"
        }
      });

      const result = await response.json().catch(() => null);

      if (!response.ok || !result || !result.success) {
        const errorMsg =
          result && result.message
            ? result.message
            : "Your complaint could not be submitted. Please check all fields and try again.";

        showMessage(formMessage, errorMsg, "error");
        return;
      }

      // Success Display
      formMessage.replaceChildren();

      const successBox = document.createElement("div");

      successBox.style.background = "#eef9f2";
      successBox.style.border = "1px solid #c7eed6";
      successBox.style.borderRadius = "10px";
      successBox.style.padding = "18px";
      successBox.style.marginTop = "14px";

      const successHeading = document.createElement("strong");

      successHeading.style.color = "#14734e";
      successHeading.style.fontSize = "15px";
      successHeading.style.display = "block";

      successHeading.textContent =
        "✓ Complaint Submitted Successfully!";

      const targetEmail =
        String(
          result.recipient_email ||
          formData.get("email") ||
          ""
        );

      const explanation = document.createElement("p");

      explanation.style.margin = "8px 0 10px";
      explanation.style.fontSize = "13px";
      explanation.style.color = "#2c4033";

      const strongEmail = document.createElement("strong");

      strongEmail.textContent = targetEmail;

      explanation.append(
        document.createTextNode(
          "Your submission has been recorded and your tracking code has been automatically sent to "
        ),
        strongEmail,
        document.createTextNode(
          ". You can also save your tracking code below to check progress anytime:"
        )
      );

      const code = document.createElement("div");

      code.className = "tracking-code-display";
      code.textContent = result.tracking_code;

      const copyButton = document.createElement("button");

      copyButton.type = "button";
      copyButton.className = "btn secondary";
      copyButton.style.marginTop = "10px";
      copyButton.textContent = "Copy tracking code";

      copyButton.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(
            result.tracking_code
          );

          copyButton.textContent =
            "✓ Code copied to clipboard!";

          setTimeout(() => {
            copyButton.textContent =
              "Copy tracking code";
          }, 3000);

        } catch {
          copyButton.textContent =
            "Please copy code manually: " +
            result.tracking_code;
        }
      });

      successBox.append(
        successHeading,
        explanation,
        code,
        document.createElement("br"),
        copyButton
      );

      formMessage.appendChild(successBox);

      complaintForm.reset();

    } catch (error) {
      console.error(
        "Complaint submission network error:",
        error
      );

      showMessage(
        formMessage,
        "Network error: Unable to connect to the server. Please verify MySQL/Apache is running in XAMPP.",
        "error"
      );

    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML =
          "Submit complaint <b>→</b>";
      }
    }
  });
}

// =============================================================================
// 2. TRACK COMPLAINT FORM
// =============================================================================

const trackForm = document.querySelector("#track-form");
const trackMessage = document.querySelector("#track-message");

if (trackForm && trackMessage) {
  trackForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!trackForm.reportValidity()) {
      return;
    }

    const trackButton =
      trackForm.querySelector('button[type="submit"]');

    const formData = new FormData(trackForm);

    const code =
      String(formData.get("code") ?? "")
        .trim()
        .toUpperCase();

    if (!code) {
      showMessage(
        trackMessage,
        "Please enter a valid tracking code.",
        "error"
      );

      return;
    }

    showMessage(
      trackMessage,
      "Checking status of complaint " + code + "...",
      "info"
    );

    if (trackButton) {
      trackButton.disabled = true;
      trackButton.textContent = "Checking...";
    }

    try {
      const response = await fetch(
        "api/track.php?code=" +
        encodeURIComponent(code),
        {
          headers: {
            "Accept": "application/json"
          }
        }
      );

      const result =
        await response.json().catch(() => null);

      if (
        !response.ok ||
        !result ||
        !result.success
      ) {
        const errorMsg =
          result && result.message
            ? result.message
            : "No complaint was found with that tracking code.";

        showMessage(
          trackMessage,
          errorMsg,
          "error"
        );

        return;
      }

      const complaint = result.data;

      const detailsList = [
        [
          "Tracking ID",
          complaint.tracking_code,
          false
        ],

        [
          "Status",
          complaint.status,
          true
        ],

        [
          "Category",
          complaint.category,
          false
        ],

        [
          "Subject",
          complaint.subject,
          false
        ],

        [
          "Submitted On",
          complaint.created_at
            ? new Date(
                complaint.created_at.replace(
                  /-/g,
                  "/"
                )
              ).toLocaleString()
            : "Recently",
          false
        ]
      ];

      if (complaint.admin_note) {
        detailsList.push([
          "Staff Response / Update",
          complaint.admin_note,
          false
        ]);
      }

      showDetails(
        trackMessage,
        detailsList
      );

    } catch (error) {
      console.error(
        "Complaint tracking network error:",
        error
      );

      showMessage(
        trackMessage,
        "Network error: Unable to check status. Please verify your connection to XAMPP.",
        "error"
      );

    } finally {
      if (trackButton) {
        trackButton.disabled = false;
        trackButton.innerHTML =
          "Check status <b>→</b>";
      }
    }
  });
}