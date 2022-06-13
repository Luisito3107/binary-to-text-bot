<?php
    /* Set timezone and fetch updates */
        date_default_timezone_set("America/Mexico_City"); $update = json_decode(file_get_contents("php://input"), false);
        if ($update->update_id == 0) {header("Location: https://t.me/BinaryToTextBot");exit;}

    /* Set main variables */
        $BOT_BASEURL = str_replace("#TOKEN#", $_ENV["BOT_TOKEN"], "https://api.telegram.org/bot#TOKEN#/");
        $debug = ($_ENV["BOT_DEBUG"] == "true");



    /* Helper functions */
        function BTTB_log($data) {
            $fileName = $_SERVER['DOCUMENT_ROOT'].'/errorlog.log';
            $fh = fopen($fileName, 'a+');
            if (is_array($data)) $data = print_r($data, 1);
            $status = fwrite($fh, $data.PHP_EOL);
            fclose($fh);
        }
        function BTTB_startsWith ($string, $startString) {$len = strlen($startString); return (substr($string, 0, $len) === $startString);}

    /* API functions */
        function BTTB_action(string $action, $postfields = false) {
            global $BOT_BASEURL; global $debug;
            if (is_object($postfields)) {$postfields = (array) $postfields;}

            $options = array(
                CURLOPT_URL => $BOT_BASEURL.$action,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postfields),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
                CURLOPT_RETURNTRANSFER => true
            );
            $ch = curl_init();
            foreach ($options as $option => $value) curl_setopt($ch, $option, $value);
            $output = curl_exec($ch);
            curl_close($ch);

            if ($debug) {
                BTTB_log($BOT_BASEURL.$action);
                BTTB_log(json_encode($postfields));
                BTTB_log($output);
            }

            return json_decode($output, true);
        }
        function BTTB_sendMessage($text, $parsemode = false, $chatid = false) {
            global $update;
            $parsemode = in_array($parsemode, array("MarkdownV2", "Markdown", "HTML")) ? $parsemode : false;
            $chatid = ($chatid ? $chatid : (is_null($update->message) ? false : $update->message->from->id));
            if ($chatid) {
                $message = (object) array();
                $message->chat_id = $chatid;
                $message->text = $text;
                if ($parsemode) {$message->parse_mode = $parsemode;}
                BTTB_action("sendMessage", $message);
            }
        }

    /* Bot functions */
        function BTTB_stringToBinary($string) {
            $characters = str_split($string);
        
            $binary = [];
            foreach ($characters as $character) {
                $data = unpack("H*", $character);
                $conversion = base_convert($data[1], 16, 2);
                for ($i=0; $i < 8-strlen($conversion); $i++) { 
                    $conversion = "0".$conversion;
                }
                $binary[] = $conversion;
            }
        
            return implode(" ", $binary);    
        }

        function BTTB_binaryToString($binary) {
            $parts = explode(' ', $binary);
        
            $string = "";
            foreach ($parts as $bin) {
                $string .= pack("H*", dechex(bindec($bin)));
            }
        
            return $string;    
        }

        function BTTB_convertBetweenTextAndBinary($string) {
            if (preg_match("/^[0-1 ]+$/i", $string)) {
                return BTTB_binaryToString($string);
            } else {
                return BTTB_stringToBinary($string);
            }
        }




    if (!is_null($update->message)) {
        $username = $update->message->from->first_name;
        $chatid = $update->message->from->id;

        if (!empty($update->message->text)) {
            $typingstatus = (object) array();
            $typingstatus->chat_id = $chatid;
            $typingstatus->action = "typing";
            BTTB_action("sendChatAction", $typingstatus);
        }

        if ($update->message->from->is_bot) die();
        

        switch($update->message->text) {
            case "/start":
                $text = "👋 <strong>Welcome, ".$username."!</strong>\n";
                $text .= "This simple bot will convert between binary and ASCII text. Just send a text and the bot will reply with the binary string, or send a binary string and the bot will reply the ASCII text string.";
                $text .= "\n\nYou can also use inline queries to convert up to 256 characters from the text field in any chat.";
            break;
            default:
                $text = BTTB_convertBetweenTextAndBinary($update->message->text);
            break;
        }


        $message = (object) array();
        $message->chat_id = $chatid;
        $message->text = $text;
        $message->parse_mode = "HTML";
        BTTB_action("sendMessage", $message);
    }

    elseif (!is_null($update->inline_query)) {
        $username = $update->inline_query->from->first_name;
        $chatid = $update->inline_query->from->id;

        if ($update->inline_query->from->is_bot) die();

        $inline_query_id = $update->inline_query->id;
        $inline_query_query = $update->inline_query->query;

        $conversion_result = BTTB_convertBetweenTextAndBinary($inline_query_query);

        $InputTextMessageContent = (object) array();
        $InputTextMessageContent->message_text = (strlen($conversion_result) > 4096 ? "Result is too long to be sent this way" : $conversion_result);

        $InlineQueryResultArticle = (object) array();
        $InlineQueryResultArticle->type = "article";
        $InlineQueryResultArticle->id = rand(1, 9999999);
        $InlineQueryResultArticle->title = (strlen($conversion_result) > 4096 ? "❌ Conversion error" : "✅ Conversion result");
        $InlineQueryResultArticle->input_message_content = $InputTextMessageContent;
        $InlineQueryResultArticle->description = substr($InputTextMessageContent->message_text, 0, min(61, strlen($InputTextMessageContent->message_text)))."...";

        $answerInlineQuery = (object) array();
        $answerInlineQuery->inline_query_id = $inline_query_id;
        $answerInlineQuery->results = [$InlineQueryResultArticle];
        $answerInlineQuery->is_personal = true;
        BTTB_action("answerInlineQuery", $answerInlineQuery);
    }