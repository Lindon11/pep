<?php

return [

    'bot_token' => env('PEPTIDE_VENDORS_BOT_TOKEN', ''),

    'webhook_secret' => env('PEPTIDE_VENDORS_WEBHOOK_SECRET', ''),

    'webhook_enabled' => env('PEPTIDE_VENDORS_WEBHOOK_ENABLED', false),

    'api_base' => 'https://api.telegram.org',

    'timeout' => 25,

    'connect_timeout' => 10,

    'welcome_enabled' => env('PEPTIDE_VENDORS_WELCOME_ENABLED', true),

    'join_verification_enabled' => env('PEPTIDE_VENDORS_JOIN_VERIFICATION', true),

    'link_spam_enabled' => env('PEPTIDE_VENDORS_LINK_SPAM_ENABLED', true),

    'welcome_message' => '<b>Welcome to the Peptide Vendors Community, {first_name}! 👋</b>

Please browse the vendor topics for product info, price lists, and contact details. If you are interested in a vendor, contact them directly via DM. To keep topics organised, please avoid posting in vendor topics.

📋 <b><u>Before posting, please read:</u></b>
Rules: https://t.me/c/4376480189/154

🛒 <b><u>Vendor Topics:</u></b>
Sellers / Single Vial: https://t.me/c/4376480189/16

📝 <b><u>Leave a review:</u></b>
Feedback / Vendor reviews: https://t.me/c/4376480189/120

⚠️ <b><u>Important:</u></b>
Vendors / sellers will not directly DM you unless you DM them first.


<i>Enjoy! 😊</i>',

    'target_chat_id' => env('PEPTIDE_VENDORS_TARGET_CHAT_ID', ''),

    'target_message_thread_id' => env('PEPTIDE_VENDORS_TARGET_MESSAGE_THREAD_ID', null),

    'limitless_token' => env('PEPTIDE_VENDORS_LIMITLESS_TOKEN', ''),
    'limitless_webhook_secret' => env('PEPTIDE_VENDORS_LIMITLESS_WEBHOOK_SECRET', ''),
];

