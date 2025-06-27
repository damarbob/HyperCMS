import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";

// Import jQuery for building action button elements
export default class ResponseProcessor {
  constructor(chatUI) {
    // Initialize chat UI reference and debug flag
    this.chatUI = chatUI;
    this.isDebug =
      window.hyper.config.environment !== "production" ? true : false;
  }

  // Process the full response object and dispatch to handlers
  process(data) {
    if (data.status !== "success") return false;

    this.processDebug(data);
    this.processReasoning(data);
    this.processMessage(data);
    this.processNote(data);
    this.processActions(data);
    this.processError(data);

    return true;
  }

  // Show raw debug output when in debug mode
  processDebug(data) {
    if (this.isDebug && data.message.debug) {
      this.chatUI.addMessage("ai", `<pre>${data.message.debug}</pre>`, {
        title: window.hyper.lang.Voltic.moduleName,
        icon: "fas fa-bolt",
      });
    }
  }

  // Render the reasoning segment of the AI reply
  processReasoning(data) {
    if (data.message.reasoning) {
      this.chatUI.addMessage(
        "ai",
        this.formatResponse(data.message.reasoning),
        {
          title: window.hyper.lang.Voltic.moduleName,
          icon: "fas fa-bolt",
          tag: window.hyper.lang.Voltic.reasoning,
          tagClass: "is-primary",
          tagIcon: "fa-solid fa-lightbulb",
          reasoning: true,
        }
      );
    }
  }

  // Render the main AI message content
  processMessage(data) {
    if (data.message.message) {
      this.chatUI.addMessage("ai", this.formatResponse(data.message.message), {
        title: window.hyper.lang.Voltic.moduleName,
        icon: "fas fa-bolt",
        tag:
          // Show the model name only in non-production environment
          window.hyper.config.environment !== "production"
            ? data.message.model
            : undefined,
      });
    }
  }

  // Render any additional note or feedback
  processNote(data) {
    if (data.message.note) {
      this.chatUI.addMessage("ai", this.formatResponse(data.message.note), {
        title: window.hyper.config.appName,
        icon: "fas fa-circle-nodes",
        tag: "Feedback",
      });
    }
  }

  // Render action buttons from system messages
  processActions(data) {
    if (!data.message.actions) return;

    data.message.system?.forEach((sysResponse) => {
      let actionsHTML = "";

      if (sysResponse.actions) {
        actionsHTML = sysResponse.actions
          .map((action) => {
            // Skip any non-button action types
            if (action.type !== "button") return "";

            // Build a jQuery control element for each button
            const $ctrl = $("<div>", { class: "control" }).append(
              $("<a>", {
                class: "button",
                href: action.href,
                target: "_blank",
              })
                .append(
                  $("<span>", { class: "icon" }).append(
                    $("<i>", { class: action.icon })
                  )
                )
                .append($("<span>", { text: action.text }))
            );

            // Return the raw HTML string for the button
            return $ctrl.get(0).outerHTML;
          })
          .join("");
      }

      // Add the group of action buttons to the chat
      this.chatUI.addMessage(
        "hyper",
        `${this.formatResponse(
          sysResponse.message
        )}<div class="field is-grouped mt-3">${actionsHTML}</div>`,
        {
          title: window.hyper.config.appName,
          icon: "fas fa-circle-nodes",
        }
      );
    });
  }

  // Display any error messages or stack traces
  processError(data) {
    if (!data.message.error) return;

    if (data.message.error.message) {
      this.chatUI.addMessage(
        "hyper",
        this.formatResponse(data.message.error.message),
        {
          title: window.hyper.config.appName,
          icon: "fas fa-circle-nodes",
        }
      );
    }

    if (data.message.error.trace) {
      this.chatUI.addMessage(
        "hyper",
        this.formatResponse(data.message.error.trace),
        {
          title: window.hyper.config.appName,
          icon: "fas fa-circle-nodes",
        }
      );
    }
  }

  // Convert objects to pretty-printed JSON or return plain text
  formatResponse(response) {
    return typeof response === "object"
      ? `<pre class="has-background-white-bis p-3"><code>${JSON.stringify(
          response,
          null,
          2
        )}</code></pre>`
      : response;
  }
}
