<?php

namespace App\Tests\Module\Main\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileResponsiveTest extends WebTestCase
{
    public function testProfileHasResponsiveViewportAndKeyboardAccessibleControls(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('meta[name="viewport"][content="width=device-width, initial-scale=1.0"]');
        $this->assertSelectorExists('link[href="/assets/main/css/profile.css"]');
        $this->assertSelectorExists('label[for="upload"]');
        $this->assertSelectorExists('button#location-trigger[type="button"][aria-haspopup="dialog"]');
        $this->assertSelectorExists('dialog#location-dialog[aria-labelledby="location-dialog-title"]');
        $this->assertSelectorExists('button#location-cancel[type="button"]');
        $this->assertNotSame('', $crawler->filter('#upload')->attr('aria-describedby'));
    }
}
