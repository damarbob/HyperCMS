export default class ChatHistory {
  // Initialize chat history with optional maximum length
  constructor(maxLength = 5) {
    this.history = [];
    this.maxLength = maxLength;
  }

  // Add a new entry and trim history to the maximum length
  add(role, content) {
    this.history.push({ role, content });
    if (this.history.length > this.maxLength) {
      this.history = this.history.slice(-this.maxLength);
    }
  }

  // Return the current history array
  get() {
    return this.history;
  }
}
