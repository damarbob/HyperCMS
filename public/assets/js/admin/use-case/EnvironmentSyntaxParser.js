// EnvironmentSyntaxParser.js

import { config } from "../../Config.js";

/**
 * Replaces environment syntax in a string. 
  UPDATE MUST ALSO BE DONE IN THE PHP VERSION IN syntax_processor_helper.php
 * @param text - The text to process.
 */
export function replaceEnvironmentSyntax(text) {
  // console.log(input);
  // Regular expression to match the pattern [ variable ]
  return text.replace(/\[\s*(\w+)\s*\]/g, function (match, key) {
    switch (key) {
      case "base_url":
        return config.baseUrl; // Assuming baseUrl is a function defined elsewhere
      // Add more variables here as needed
      // case 'other_variable':
      //     return 'your_value_here';
      default:
        return match; // Return original if no match
    }
  });
}
