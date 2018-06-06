Feature: Schedule common features

    Background:
        Given for the client "ctp"
        And I am on "/schedule"

#    @javascript
# TODO: rewrite test without YML
#    Scenario Outline: Stop schedules with UC
#        When I click on "#ctp-searchSchedule" accordion
#        And I fill in "schedule_stop_area_autocomplete" with autocomplete "<stop_area>"
#        And I submit the "schedule" form
#        Then I am redirected to "/schedule/result/"
#        And I have "<expected>" schedule result
#
#        Examples:
#        schedule
#
#    @javascript
# TODO: rewrite test without YML
#    Scenario Outline: Multimodal with UC
#        When I click on "#ctp-multimodalSchedule" accordion
#        And I fill in "search_from_autocomplete" with autocomplete "<from>" in "multimodal" form
#        And I fill in "search_to_autocomplete" with autocomplete "<to>" in "multimodal" form
#        And I submit the "multimodal" form
#        Then I am redirected to "/schedule/multimodal/result/"
#        And I have "<expected>" multimodal result
#
#        Examples:
#        multimodal
#
#    @javascript
# TODO: rewrite test without YML
#    Scenario Outline: Line schedules with UC
#        When I click on "#lineSchedule" accordion
#        And I fill in "lineSchedule_network" with option "<network>" waiting for "lineSchedule_line"
#        And I fill in "lineSchedule_line" with option "<line>" waiting for "lineSchedule_route"
#        And I fill in "lineSchedule_route" with option "<route>"
#        And I submit the "lineSchedule" form
#        Then I am redirected to "/schedule/line/result/"
#        And I have "<expected>" line schedule result
#
#        Examples:
#        line_schedule
