export default class ChatService {
  // Initializes the ChatService instance
  constructor() {}

  // Sends the chat history to the backend and returns the JSON response
  async send(history) {
    // Perform POST request with JSON body and CSRF headers
    const response = await fetch(
      `${window.hyper.config.baseUrl}admin/voltic/ask`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          [window.hyper.config.csrfHeader]: window.hyper.config.csrfHash,
        },
        body: JSON.stringify(history),
      }
    );

    // Throw on HTTP errors
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

    // Parse and return the response JSON
    return await response.json();
  }
}
