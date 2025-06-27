import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";
import { marked } from "https://cdn.jsdelivr.net/npm/marked/lib/marked.esm.js";

// Manages chat UI rendering, message insertion, and scrolling
export default class ChatUI {
  // Initialize with a parent container selector and render the UI
  constructor(containerSelector = "#chatContainer") {
    this.$parentElement = $(containerSelector);
    this.render();
  }

  // Build the chat layout: header, messages area, and input form
  render() {
    this.$container = $("<div>", {
      id: "volticChatContainer",
      class: "chat-container pt-5",
      append: [
        $("<div>", {
          class: "chat-header has-text-centered mb-5",
          append: [
            $("<h1>", {
              class: "title is-4",
              text: window.hyper.lang.Voltic.moduleName,
            }),
            $("<p>", {
              class: "subtitle is-7",
              text: window.hyper.config.appName,
            }),
          ],
        }),
        $("<div>", {
          class: "messages",
          id: "messages",
        }),
        (this.$chatForm = $("<form>", {
          class: "box",
          css: { position: "sticky", bottom: "1rem" },
          append: [
            $("<div>", {
              class: "field",
              append: $("<div>", {
                class: "control is-expanded",
                append: (this.$messageInput = $("<textarea>", {
                  class: "input is-shadowless",
                  placeholder: window.hyper.lang.Voltic.askVoltic,
                  autocomplete: "off",
                  "aria-label": window.hyper.lang.Voltic.chatInput,
                  css: { maxHeight: "200px" },
                  rows: "1",
                })),
              }),
            }),
            $("<div>", {
              class: "is-flex",
              append: [
                $("<div>", {
                  class: "is-flex-grow-1",
                  append: $("<p>", {
                    class: "help is-size-7",
                    text: window.hyper.lang.Voltic.shiftEnterAddNewLine,
                  }),
                }),
                $("<div>", {
                  class: "field",
                  append: $("<div>", {
                    class: "control",
                    append: (this.$submitButton = $("<button>", {
                      type: "submit",
                      class: "button is-primary has-tooltip-arrow",
                      "data-tippy-content": window.hyper.lang.Voltic.sendChat,
                      append: $("<span>", {
                        class: "icon",
                        append: $("<i>", {
                          class: "fas fa-paper-plane",
                        }),
                      }),
                    })),
                  }),
                }),
              ],
            }),
          ],
        })),
      ],
    });

    // Append the constructed chat container and store messages area
    this.$parentElement.append(this.$container);
    this.$messagesContainer = $("#messages");
  }

  // Create a single message element with metadata and content
  createMessageElement(className, metaContent, messageContent) {
    return $("<div>", {
      class: `message ${className}`,
      append: [
        $("<div>", {
          class: "message-body",
          append: [
            $("<div>", {
              class: "content",
              append: [
                $("<div>", {
                  class: "message-meta",
                  append: metaContent,
                }),
                $("<div>", {
                  class: "message-content",
                  html: messageContent,
                }),
              ],
            }),
          ],
        }),
      ],
    });
  }

  // Add a new chat message, parse markdown, and scroll to bottom
  addMessage(role, content, options = {}) {
    const metaContent = [
      $("<span>", {
        class: "icon",
        append: $("<i>", {
          class: options.icon || "fas fa-user",
        }),
      }),
      $("<span>", {
        class: "has-text-weight-semibold",
        text: options.title || window.hyper.data.username,
      }),
      options.tag
        ? $("<span>", {
            class: `tag ${options.tagClass || ""}`,
            append: [
              options.tagIcon
                ? $("<span>", {
                    class: "mr-2",
                    append: $("<i>", { class: options.tagIcon }),
                  })
                : "",
              $("<span>", { text: options.tag }),
            ],
          })
        : "",
      $("<small>", {
        class: "ml-2",
        text: new Date().toLocaleTimeString(),
      }),
    ];

    const $messageEl = this.createMessageElement(
      `${role}-message ${options.reasoning ? "ai-reasoning" : ""}`,
      metaContent,
      marked.parse(content)
    );

    // Append to message container and auto-scroll
    this.$messagesContainer.append($messageEl);
    this.scrollToBottom();
  }

  // Smoothly scroll window to show the latest message
  scrollToBottom() {
    requestAnimationFrame(() => {
      window.scrollTo({
        top: document.body.scrollHeight,
        behavior: "smooth",
      });
    });
  }
}
