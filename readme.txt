=== NodeThemes AI Chat - AI Customer Support Chat ===
Contributors: nodethemes
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

An AI assistant that answers your customers in a live chat on your website, with your own OpenAI / Claude / Gemini API key and what you tell it about your business.

== Description ==
* Live chat bubble on your website (works on phones, right-to-left languages and with page caching).
* Answers with OpenAI (ChatGPT), Anthropic (Claude) or Google Gemini, using your own API key.
* Describe your business and give instructions in your own words (prompt), plus a Knowledge list of questions and answers.
* The assistant never invents prices or policies: questions it cannot answer from your information are passed to you ("Needs you" in the Inbox, plus an e-mail).
* Inbox: read every conversation, answer customers yourself, switch the AI off per conversation.
* Honest by design: it never claims to be a person.
* Daily budget and request limits so the AI bill cannot run away.

== Free and Pro ==
The free plugin includes 50 AI replies per calendar month. After that, messages still arrive in your Inbox and you get an e-mail, but the AI waits until the next month.
Pro (NodeThemes AI Chat Pro, a separate add-on sold at nodethemes.com) adds: unlimited AI replies, Telegram (bot and Telegram Business), WhatsApp (official Cloud API, or your normal WhatsApp by QR code through your own gateway), human-like reply timing with typing indicator, learning from your answers, questions on your Telegram, WooCommerce product answers and a backup AI provider.
The free plugin only contacts nodethemes.com to check for new versions of itself, and your AI provider to write answers.

== Setup ==
1. Upload and activate.
2. AI Chat > Settings > AI: paste your API key, press "Test connection".
3. AI Chat > Settings > Business & answers: describe your business.
4. Open your website and send a test message in the chat bubble. Answers you see in AI Chat > Inbox.

Tip: keep keys out of the database with define( 'NTAC_OPENAI_KEY', '...' ); (or NTAC_ANTHROPIC_KEY / NTAC_GEMINI_KEY) in wp-config.php.

== Changelog ==
= 1.0.0 =
* First release.
