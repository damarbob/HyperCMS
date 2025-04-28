// i18n.js
import i18next from "https://deno.land/x/i18next/index.js";
import { en } from "./En.js";

i18next.init({
  lng: "en",
  debug: true,
  resources: { en },
});

export default i18next;
