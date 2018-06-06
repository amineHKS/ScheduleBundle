<?php

namespace CanalTP\ScheduleBundle\Features\Context;

use CanalTP\AcceptanceTestBundle\Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements SnippetAcceptingContext
{
    /**
     * @Then /^I have "(?P<expected>[^"]*)" schedule result$/
     */
    public function iHaveScheduleResult($expected)
    {
        switch ($expected) {
            case null:
                $this->assertElementNotEmpty('#ctp-result-map-schedule');
                break;
            case 'toolbox':
                $this->assertElementOnPage('.ctp-toolbox');
                break;
            case 'schedule_directions':
                $this->assertElementVisible('.ctp-clickable-list');
                break;
            default:
                throw new \Exception(sprintf('Expected schedule result not supported: %s', $expected));
        }
    }

    /**
     * @Then /^I have "(?P<expected>[^"]*)" multimodal result$/
     */
    public function iHaveMultimodalResult($expected)
    {
        switch ($expected) {
            case null:
                $this->assertElementVisible('#ctp-journey-1') && $this->assertElementNotEmpty('#ctp-result-map');
                break;
            case 'toolbox':
                $this->assertElementOnPage('.ctp-toolbox');
                break;
            default:
                throw new \Exception(sprintf('Expected multimodal result not supported: %s', $expected));
        }
    }

    /**
     * @Then /^I have "(?P<expected>[^"]*)" line schedule result$/
     */
    public function iHaveLineScheduleResult($expected)
    {
        switch ($expected) {
            case null:
                $this->assertElementNotEmpty('#ctp-result-line-schedule');
                break;
            case 'toolbox':
                $this->assertElementOnPage('.ctp-toolbox');
                break;
            case 'schedule_directions':
                $this->assertElementVisible('.ctp-clickable-list');
                break;
            default:
                throw new \Exception(sprintf('Expected line schedule result not supported: %s', $expected));
        }
    }
}
