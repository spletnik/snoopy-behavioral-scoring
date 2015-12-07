# Piwik Snoopy Plugin

## Description

Plugin enables you to score your visitors based on actions they do on your websites. You can easily distinguish users that are reguralliy active on your webapge from those that come just once.

## Installation
### Automatic
Install it via Piwik Marketplace and [configure](#configuration) plugin.
### Manual installation
1. Download whole repository as ZIP.
2. Go to your piwik Administration => Marketplace and click on "upload a plugin in .zip format."
3. After plugin is upladed press "Activate plugin"

If you did everything correct and you have sufficient permissions on folder plugins, Snoopy should be successfully installed.

Now all you need to do is to set your configuration and create cron that calculate visitors scores.

## Configuration

Before Snoopy starts scooring visitors you first have to tweak some configuration to match your needs.

1. Matching site
You have to select for which site your Snoopy scores visitors. For now only one webpage tracking is available.

2. Matching goal
Snoopy will start scooring only when specific goal is achieved. You can choose one or more goals as entry point to start scooring.

3. Cooling factor
Cooling factor is number that tells you how fast visitors will loose their score.

Here is example simalation for factor 1.1

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

**0 * * * * /piwik_root/console snoopy:recalculate-score**

Additionally you can pipe log to file:

**0 * * * * /piwik_root/console snoopy:recalculate-score >> /var/log/snoopy.log**
## FAQ

__When does my visitors gets scored.__

When they reach specified goal in plugin settings.

## Changelog

0.1.0 - Initial version

## Support

Please direct any feedback to info@spletnik.si
