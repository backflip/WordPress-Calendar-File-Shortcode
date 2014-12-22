# Calendar File Shortcode

Shortcode to render a link to an .ics calendar file.

## Warning

I don't really understand PHP. It's weird.

## Usage

### Minimal example
[calendar start="31.12.2014 22:00" end="01.01.2015 05:00" title="Party"]

### Options
- ```start```: **Mandatory**. Example: "31.12.2014 22:00". Has to be parseable by [strtotime](http://php.net/manual/en/function.strtotime.php).
- ```end```: **Mandatory**. Example: "31.12.2014 22:00". Has to be parseable by [strtotime](http://php.net/manual/en/function.strtotime.php).
- ```title```: **Mandatory**. Example: "Party"
- ```location```: Example: "Kugl, St. Gallen". Default: empty.
- ```description```: Example: "Incredibly awesome party.". Default: empty.
- ```link```: Example: "Incredibly awesome party.". Default: empty.
- ```filename```: Used for .ics file. Default: "entry.ics".
- ```linktext```: Title of download link. Default: "Add to calendar".
- ```linkclass```: CSS class applied to download link. Default: "calendar".