<?php
/**
 * Telegram Bot
 *
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2020/2/3
 * @time 15:23
 */

namespace Luolongfei\Libs\MessageServices;

use GuzzleHttp\Client;
use Luolongfei\Libs\Log;
use Luolongfei\Libs\Connector\MessageGateway;

class TelegramBot extends MessageGateway
{
    const TIMEOUT = 33;

    /**
     * @var string chat_id
     */
    protected $chatID;

    /**
     * @var string bot token
     */
    protected $token;

    /**
     * @var string Telegram host address
     */
    protected $host;

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
        $this->chatID = config('message.telegram.chat_id');
        $this->token = config('message.telegram.token');
        $this->host = $this->getTelegramHost();

        $this->client = new Client([
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'cookies' => false,
            'timeout' => self::TIMEOUT,
            'verify' => config('verify_ssl'),
            'debug' => config('debug'),
            'proxy' => config('message.telegram.proxy'),
        ]);
    }

    /**
     * Get Telegram Host Address
     *
     * @return string
     */
    private function getTelegramHost()
    {
        $host = (string)config('message.telegram.host');

        if (preg_match('/^(?:https?:\/\/)?(?P<host>[^\/?\n]+)/iu', $host, $m)) {
            return $m['host'];
        }

        return 'api.telegram.org';
    }

    /**
     * Generate the full text of the domain status MarkDown
     *
     * @param string $username
     * @param array $domainStatus
     *
     * @return string
     */
    public function genDomainStatusFullMarkDownText(string $username, array $domainStatus)
    {
        $markDownText = sprintf(lang('100102'), $username);

        $markDownText .= $this->genDomainStatusMarkDownText($domainStatus);

        $markDownText .= $this->getMarkDownFooter();

        return $markDownText;
    }

    /**
     * Get Footer MarkDown
     *
     * @return string
     */
    public function getMarkDownFooter()
    {
        $footer = '';

        $footer .= lang('100103');

        return $footer;
    }

    /**
     * Generate domain name status MarkDown text
     *
     * @param array $domainStatus
     *
     * @return string
     */
    public function genDomainStatusMarkDownText(array $domainStatus)
    {
        if (empty($domainStatus)) {
            return lang('100105');
        }

        $domainStatusMarkDownText = '';

        foreach ($domainStatus as $domain => $daysLeft) {
            $domainStatusMarkDownText .= sprintf(lang('100106'), $domain, $domain, $daysLeft);
        }

        $domainStatusMarkDownText = rtrim(rtrim($domainStatusMarkDownText, ' '), "???,\n") . lang('100107');

        return $domainStatusMarkDownText;
    }

    /**
     * Generate domain renewal results MarkDown text
     *
     * @param string $username
     * @param array $renewalSuccessArr
     * @param array $renewalFailuresArr
     * @param array $domainStatus
     *
     * @return string
     */
    public function genDomainRenewalResultsMarkDownText(string $username, array $renewalSuccessArr, array $renewalFailuresArr, array $domainStatus)
    {
        $text = sprintf(lang('100108'), $username);

        if ($renewalSuccessArr) {
            $text .= lang('100109');
            $text .= $this->genDomainsMarkDownText($renewalSuccessArr);
        }

        if ($renewalFailuresArr) {
            $text .= lang('100110');
            $text .= $this->genDomainsMarkDownText($renewalFailuresArr);
        }

        $text .= lang('100111');
        $text .= $this->genDomainStatusMarkDownText($domainStatus);

        $text .= $this->getMarkDownFooter();

        return $text;
    }

    /**
     * Generate domain name MarkDown text
     *
     * @param array $domains
     *
     * @return string
     */
    public function genDomainsMarkDownText(array $domains)
    {
        $domainsMarkDownText = '';

        foreach ($domains as $domain) {
            $domainsMarkDownText .= sprintf("[%s](http://%s) ", $domain, $domain);
        }

        $domainsMarkDownText = trim($domainsMarkDownText, ' ') . "\n";

        return $domainsMarkDownText;
    }

    /**
     * ?????? MarkDown ???????????????????????????
     *
     * @param string $markDownTable
     *
     * @return array
     */
    public function getMarkDownRawArr(string $markDownTable)
    {
        $rawArr = [];
        $markDownTableArr = preg_split("/(?:\n|\r\n)+/", $markDownTable);

        foreach ($markDownTableArr as $row) {
            $row = (string)preg_replace('/^\s+|\s+$|\s+|(?<=\|)\s+|\s+(?=\|)/', '', $row);

            if ($row === '') {
                continue;
            }

            $rowArr = explode('|', trim($row, '|'));
            $rawArr[] = $rowArr;
        }

        return $rawArr;
    }

    /**
     * Sending message
     *
     * @param string $content Support markdown syntax, but remember to escape the non-markdown parts
     * @param string $subject
     * @param integer $type
     * @param array $data
     * @param string|null $recipient The chat_id parameter can be specified separately
     * @param mixed ...$params
     *
     * @desc
     * Be careful to escape the characters occupied by markdown tags, otherwise they will not be sent correctly. According to the official instructions, the following characters are not recognized by Telegram Bot as markdown tags if you do not want them to be
     * should be escaped and passed in, the official instructions are as follows???
     * In all other places characters '_???, ???*???, ???[???, ???]???, ???(???, ???)???, ???~???, ???`???, ???>???, ???#???, ???+???, ???-???, ???=???, ???|???,
     * ???{???, ???}???, ???.???, ???!??? must be escaped with the preceding character ???\'.
     * ?????????????????????????????? 400 ??????
     *
     * ??????markdown???????????????
     * *bold \*text*
     * _italic \*text_
     * __underline__
     * ~strikethrough~
     * *bold _italic bold ~italic bold strikethrough~ __underline italic bold___ bold*
     * [inline URL](http://www.example.com/)
     * [inline mention of a user](tg://user?id=123456789)
     * `inline fixed-width code`
     * ```
     * pre-formatted fixed-width code block
     * ```
     * ```python
     * pre-formatted fixed-width code block written in the Python programming language
     * ```
     * ??????????????????????????? markdown ????????????????????????????????????**??????**????????????????????? Telegram Bot ?????????*?????????*????????????
     * ????????????????????????????????????https://core.telegram.org/bots/api#sendmessage
     * ?????????????????????_?????????~?????????-?????????.?????????>??????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
     * ?????????????????????????????????????????????????????????????????????????????? telegram ???????????????
     *
     * ?????? telegram bot ??? markdown ????????????????????????https://core.telegram.org/bots/api#markdownv2-style???????????????????????????????????????
     * ????????????????????????????????? telegram bot ????????????
     *
     * @return bool
     */
    public function send(string $content, string $subject = '', int $type = 1, array $data = [], ?string $recipient = null, ...$params)
    {
        $this->check($content, $data);

        $commonFooter = '';

        if ($type === 1 || $type === 4) {
            $this->setCommonFooter($commonFooter, "\n", false);
        } else if ($type === 2) {
            $this->setCommonFooter($commonFooter, "\n", false);
            $content = $this->genDomainRenewalResultsMarkDownText($data['username'], $data['renewalSuccessArr'], $data['renewalFailuresArr'], $data['domainStatusArr']);
        } else if ($type === 3) {
            $this->setCommonFooter($commonFooter);
            $content = $this->genDomainStatusFullMarkDownText($data['username'], $data['domainStatusArr']);
        } else {
            throw new \Exception(lang('100003'));
        }

        $content .= $commonFooter;

        $isMarkdown = true;

        // ???????????????????????? telegram ?????????????????????????????????
        if ($params && isset($params[1]) && $params[0] === 'TG') {
            $isMarkdown = $params[1];
        }

        if ($subject !== '') {
            $content = $subject . "\n\n" . $content;
        }

        if ($isMarkdown) {
            // ???????????????????????????????????????????????????????????????????????????????????????????????????????????????
            $content = preg_replace('/([.>~_{}|`!+=#-])/u', '\\\\$1', $content);

            // ???????????????????????? [] ?????? ()
            $content = preg_replace_callback_array(
                [
                    '/(?<!\\\\)\[(?P<brackets>.*?)(?!\]\()(?<!\\\\)\]/' => function ($match) {
                        return '\\[' . $match['brackets'] . '\\]';
                    },
                    '/(?<!\\\\)(?<!\])\((?P<parentheses>.*?)(?<!\\\\)\)/' => function ($match) {
                        return '\\(' . $match['parentheses'] . '\\)';
                    }
                ],
                $content
            );
        }

        try {
            $resp = $this->client->post(
                sprintf('https://%s/bot%s/sendMessage', $this->host, $this->token),
                [
                    'form_params' => [
                        'chat_id' => $recipient ?: $this->chatID,
                        'text' => $content, // Text of the message to be sent, 1-4096 characters after entities parsing
                        'parse_mode' => $isMarkdown ? 'MarkdownV2' : 'HTML',
                        'disable_web_page_preview' => true,
                        'disable_notification' => false
                    ],
                ]
            );

            $resp = json_decode((string)$resp->getBody(), true);

            return $resp['ok'] ?? false;
        } catch (\Exception $e) {
            system_log(sprintf(lang('100112'), $e->getMessage()));

            return false;
        }
    }
}
