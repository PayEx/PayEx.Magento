<?php

class PayEx_Payments_FeedController extends Mage_Core_Controller_Front_Action
{
    /**
     * Index Action
     * @return void
     */
    public function indexAction()
    {
        try {
            $client = new Zend_Http_Client('https://api.github.com/repos/PayEx/PayEx.Magento/releases/latest');
            $client->setHeaders('Accept', 'application/vnd.github.v3+json');
            $response = $client->request();

            if ((int)$response->getStatus() / 100 !== 2) {
                throw new Exception('Request failed');
            }

            $data = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            $this->getResponse()
                 ->setHeader('HTTP/1.1', 200, true)
                 ->setHeader('Content-Type', 'application/atom+xml', true)
                 ->setBody("<?xml version=\"1.0\"?>");
            return;
        }

        $version = ltrim($data['tag_name'], 'v');
        $xml = "<?xml version=\"1.0\"?>
<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">
    <channel>
        <title>PayEx Payments</title>
        <link>{$data['html_url']}</link>
        <description>PayEx Payments</description>
        <language>en</language>
        <lastBuildDate>{$data['published_at']}</lastBuildDate>
        <item>
            <title>PayEx Payments</title>
            <link>{$data['html_url']}</link>
            <description><![CDATA[PayEx Payments]></description>
            <version>{$version}</version>
            <code>PayEx_Payments</code>
        </item>
    </channel>
</rss>
";

        $this->getResponse()
             ->setHeader('HTTP/1.1', 200, true)
             ->setHeader('Content-Type', 'application/atom+xml', true)
             ->setBody($xml);
    }
}
