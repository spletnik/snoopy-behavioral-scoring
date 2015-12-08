# Piwik Snoopy Plugin

## Description

Snoopy is a User behaviour scoring plugin for piwik. It allows you to score your visitors depending on goals reached, pages visited, email campaigns opened and other factors. In other words this plugin enables you to score your visitors based on actions they do on your website. It has a robust scoring methodology for heating and cooling visitor score.

## Installation
### Automatic
Install it via Piwik Marketplace and [configure](#configuration) plugin.
### Manual installation
1. Download whole repository as ZIP.
2. Go to your piwik Administration => Marketplace and click on "upload a plugin in .zip format."
3. After plugin is upladed press "Activate plugin"

If you did everything correct and you have sufficient permissions on folder plugins, Snoopy should be successfully installed.

Now all you need to do is to set your configuration and create a cron that calculates visitors scores.

## Configuration

### Required
Before Snoopy starts scoring visitors you first have to tweak some configuration to match your needs.

1. Matching site:
You have to select for which site your Snoopy scores visitors. For now only one website property tracking is available.

2. Matching goal:
Snoopy will start scoring only when specific goal is achieved. You can choose one or more goals as entry point to start .ing.

3. Cooling factor:
Cooling factor is number that tells you how fast visitors will loose their score.

Here is example simulation for factor 1.1

|Number of inactive days|Current penalty|Penalty since beginning|Visitor scores	|
|-----------------------|---------------|-----------------------|---------------|
|						|				|						|	100		  	|
|	1					|	1			|	1					|	99		  	|
|	2					|	1.1			|	2.1					|	97.9		|
|	3					|	1.21		|	3.31				|	96.69		|
|	4					|	1.331		|	4.641				|	95.359		|
|	5					|	1.4641		|	6.1051				|	93.8949		|
|	6					|	1.61051		|	7.71561				|	92.28439	|
|	7					|	1.771561	|	9.487171			|	90.512829	|
|	8					|	1.9487171	|	11.4358881			|	88.5641119	|
|	9					|	2.14358881	|	13.57947691			|	86.42052309	|
|	10					|	2.357947691	|	15.9374246			|	84.0625754	|

From the table you can see that on first inactive day you loose 1 point, 
on the  second inactive day you loose 1*factor point
on third inactive day you loose second_day*factor point etc.

Those configuration parameters are mandatory for Snoopy to score propperly. 

When you are finished with configuration you just need to put this comand as your cronjob and Snoopy will start to calculate your score (This will recalcualte score hourly):

**0 * * * * /piwik_root/console snoopybehavioralscoring:recalculate-score**

Additionally you can pipe log to file:

**0 * * * * /piwik_root/console snoopybehavioralscoring:recalculate-score >> /var/log/snoopy.log**

### Optional
- Enable console full debug

When running recalculate-score some additional info is printed, that could be usefull when debuging why score is not calculated properly.

- Campaign entry bonus

Bonus score that is added when visitor visits your webpage trough campaign (Google Analytics campaign parameters are set)
- Special URL's

URL;SCORE pairs that make some of your URL's worth more score points. 

For example:

http://example.com/contact;3

Here contact page is worth 3 points instead just one.
## API
Snoopy also has API that can be used, to integrate it with third party apps and send some additional info to match know your visitors better.

Here are all API available at the moment:

- SnoopyBehavioralScoring.getVisitorsScores

Returns scores for all visitors that has been scored
- SnoopyBehavioralScoring.getVisitorIdsToScore

Returns visitors ID's that has reaches goals that specify start of scoring.
- SnoopyBehavioralScoring.getScore(idvisitor)

Get score for specific visitor ID.
- SnoopyBehavioralScoring.storeVisitorData (idvisitor, data)

Store visitor data. Data is passed as json and can have 5 fields. (custom_1,custom_2, ... custom_5)

custom_1 is reseved for email address.
custom_5 is reserved for json info about visitor. If info includes email, it will be stored into custom_1 automatically.
- SnoopyBehavioralScoring.getVisitorEmail(idvisitor)

Returns email for specific visitor ID. 
- SnoopyBehavioralScoring.getVisitorEmailFromJson(json_data)

This method tries to find email in provided json. It recursively goes trough json and try to find emails. 
- SnoopyBehavioralScoring.matchVisitorsEmail

Loops trough all visitors and try to find their email addresses from json in custom_5 field. Email is not stored.
- SnoopyBehavioralScoring.storeVisitorsEmail

Stores visitor email address in custom_1 field. Email is find from json in custom_5 field.
- SnoopyBehavioralScoring.findHeatStatus(idvisitor)

Find heat status for specific visitor. If visitor's score is increased from past day than visitor is heating up, otherwise visitor is cooling down. Visitors that has the same score has either marked as new (score is same since last day and is not 0) or idle (score is 0)
- SnoopyBehavioralScoring.storeHeatStatuses

Find heat statuses for all visitors and store them.
- SnoopyBehavioralScoring.heatStatus(idvisitor)

Returns already stored heat status for specific visitor.
## FAQ

__When does my visitors gets scored.__

When they reach specified goal in plugin settings.

## Changelog

0.1.1 - Readme changes + minor fixes
0.1.0 - Initial version

## Support

Please direct any feedback to info@spletnik.si
