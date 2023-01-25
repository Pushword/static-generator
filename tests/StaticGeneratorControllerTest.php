<?php

namespace Pushword\StaticGenerator\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;

class StaticGeneratorControllerTest extends AbstractAdminTest
{
    public function testController()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/static');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/admin/static/localhost.dev');
        $this->assertResponseIsSuccessful();
    }
}
