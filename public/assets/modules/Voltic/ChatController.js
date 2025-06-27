import ChatHistory from "./ChatHistory.js";
import ChatService from "./ChatService.js";
import ChatUI from "./ChatUI.js";
import InputHandler from "./InputHandler.js";
import ResponseProcessor from "./ResponseProcessor.js";

export default class ChatController {
  constructor() {
    // Initialize UI, history, service, input handling, and response processing
    this.chatUI = new ChatUI();
    this.form = this.chatUI.$chatForm[0];
    this.input = this.chatUI.$messageInput[0];
    this.submitButton = this.chatUI.$submitButton[0];
    this.chatHistory = new ChatHistory(5); // Keep last 5 messages
    this.chatService = new ChatService(); // Handles API calls
    this.inputHandler = new InputHandler({
      // Enable/disable form
      form: this.form,
      input: this.input,
      submitButton: this.submitButton,
    });
    this.responseProcessor = new ResponseProcessor(this.chatUI); // Renders AI replies
  }

  // Kick off event listeners and show welcome text
  init() {
    this.inputHandler.init();
    this.setupEventListeners();
    this.showWelcomeMessages();
  }

  // Bind form submit to handler
  setupEventListeners() {
    this.form.addEventListener("submit", this.handleSubmit.bind(this));
  }

  // Show initial greetings
  showWelcomeMessages() {
    this.chatUI.addMessage("user", window.hyper.lang.Voltic.helloVoltic, {
      title: window.hyper.data.username,
      icon: "fas fa-user",
    });
    this.chatUI.addMessage("ai", window.hyper.lang.Voltic.introduction, {
      title: window.hyper.lang.Voltic.moduleName,
      icon: "fas fa-bolt",
    });
  }

  // Handle user submit: display, send, process response, handle errors
  async handleSubmit(e) {
    e.preventDefault();
    const message = this.input.value.trim();
    if (!message) return; // Ignore empty

    this.inputHandler.disable(); // Block input during request
    this.chatUI.addMessage("user", message, {
      // Display user message
      title: window.hyper.lang.Voltic.username,
      icon: "fas fa-user",
    });
    this.chatHistory.add("user", message); // Append to history
    this.input.value = ""; // Clear field

    try {
      const response = await this.chatService.send(this.chatHistory.get());
      if (!this.responseProcessor.process(response)) {
        // Fallback on invalid response
        throw new Error(
          response.message ||
            window.hyper.lang.Voltic.unknownErrorPleaseContactSupport
        );
      }
    } catch (error) {
      // Log and show error in chat
      console.error(
        window.hyper.util.text.replacePlaceholders(
          window.hyper.lang.Voltic.volticExperiencingIssuesx,
          { x: error.message }
        ),
        error
      );
      this.chatUI.addMessage(
        "hyper",
        window.hyper.util.text.replacePlaceholders(
          window.hyper.lang.Voltic.volticExperiencingIssuesx,
          { x: error.message }
        ),
        {
          title: window.hyper.config.appName,
          icon: "fas fa-circle-nodes",
        }
      );
    } finally {
      this.inputHandler.enable(); // Re-enable input
    }
  }
}
