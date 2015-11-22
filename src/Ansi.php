<?php
/**
 * Simple ANSI Colors
 * Version 1.0.0
 * https://github.com/SimonEast/Simple-Ansi-Colors
 *
 * Helper class that replaces the following tags into the appropriate
 * ANSI color codes
 *
 *      <black>
 *      <red>
 *      <green>
 *      <yellow>
 *      <blue>
 *      <magenta>
 *      <cyan>
 *      <white>
 *      <gray>
 *      <darkRed>
 *      <darkGreen>
 *      <darkYellow>
 *      <darkBlue>
 *      <darkMagenta>
 *      <darkCyan>
 *      <darkWhite>
 *      <darkGray>
 *      <bgBlack>
 *      <bgRed>
 *      <bgGreen>
 *      <bgYellow>
 *      <bgBlue>
 *      <bgMagenta>
 *      <bgCyan>
 *      <bgWhite>
 *      <bold>          Not visible on Windows
 *      <italics>       Not visible on Windows
 *      <reset>         Clears all colors and styles (required)
 *
 * Note: we don't use commands like bold-off, underline-off as it was introduced
 * in ANSI 2.50+ and does not currently display on Windows using ANSICON
 */

namespace Banago\PHPloy;

class Ansi
{
    /**
     * Whether color codes are enabled or not
     *
     * Valid options:
     *     null - Auto-detected. Color codes will be enabled on all systems except Windows, unless it
     *            has a valid ANSICON environment variable
     *            (indicating that ANSICON is installed and running)
     *     false - will strip all tags and NOT output any ANSI color codes
     *     true - will always output color codes
     */
    public static $enabled = null;

    public static $tags = array(
        '<black>'       => "\033[0;30m",
        '<red>'         => "\033[1;31m",
        '<green>'       => "\033[1;32m",
        '<yellow>'      => "\033[1;33m",
        '<blue>'        => "\033[1;34m",
        '<magenta>'     => "\033[1;35m",
        '<cyan>'        => "\033[1;36m",
        '<white>'       => "\033[1;37m",
        '<gray>'        => "\033[0;37m",
        '<darkRed>'     => "\033[0;31m",
        '<darkGreen>'   => "\033[0;32m",
        '<darkYellow>'  => "\033[0;33m",
        '<darkBlue>'    => "\033[0;34m",
        '<darkMagenta>' => "\033[0;35m",
        '<darkCyan>'    => "\033[0;36m",
        '<darkWhite>'   => "\033[0;37m",
        '<darkGray>'    => "\033[1;30m",
        '<bgBlack>'     => "\033[40m",
        '<bgRed>'       => "\033[41m",
        '<bgGreen>'     => "\033[42m",
        '<bgYellow>'    => "\033[43m",
        '<bgBlue>'      => "\033[44m",
        '<bgMagenta>'   => "\033[45m",
        '<bgCyan>'      => "\033[46m",
        '<bgWhite>'     => "\033[47m",
        '<bold>'        => "\033[1m",
        '<italics>'     => "\033[3m",
        '<reset>'       => "\033[0m",
    );

    /**
     * This is the primary function for converting tags to ANSI color codes
     * (see the class description for the supported tags)
     *
     * For safety, this function always appends a <reset> at the end, otherwise the console may stick
     * permanently in the colors you have used.
     *
     * @param string $string
     * @return string
     */
    public static function tagsToColors($string)
    {
        if (static::$enabled === null) {
            static::$enabled = !static::isWindows() || static::isAnsiCon();
        }

        if (!static::$enabled) {
            // Strip tags (replace them with an empty string)
            return str_replace(array_keys(static::$tags), '', $string);
        }

        // We always add a <reset> at the end of each string so that any output following doesn't continue the same styling
        $string .= '<reset>';
        return str_replace(array_keys(static::$tags), static::$tags, $string);
    }

    public static function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public static function isAnsiCon()
    {
        return !empty($_SERVER['ANSICON'])
            && substr($_SERVER['ANSICON'], 0, 1) != '0';
    }
}
