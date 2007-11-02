<html>
<head>
<?php

/** Represents one character.  Style holds any amount of HTML that does
 * not take up any space--e.g. span tags, bold, italics, etc.
 */
class Cell
{
    var $style;
    var $data;

    function __construct()
    {
        $this->data = '&nbsp;';
    }

    function set_style($foreground, $background, $blinking)
    {
        $this->style = "</span><span style=color:$foreground";
        if ($background)
            $this->style .= ";background:$background";
        if ($blinking)
            $this->style .= ';text-decoration:blink';
        $this->style .= '>';
    }

    function set_data($d)
    {
        if ($d == " ")
            $this->data = '&nbsp;';
        else
            $this->data = $d;
    }

    function print_cell()
    {
        echo $this->style;
        echo $this->data;
    }
}


/** Represents one row or line of characters. */
class Row
{
    var $cells = array();

    function get_cell($c)
    {
        if (!array_key_exists($c, $this->cells))
            $this->cells[$c] = new Cell();
        return $this->cells[$c];
    }

    function set_style($col, $foreground, $background, $blinking)
    {
        $this->get_cell($col)->set_style($foreground, $background, $blinking);
    }

    function set_data($col, $data)
    {
        $this->get_cell($col)->set_data($data);
    }

    function print_row()
    {
        $last = 0;
        foreach ($this->cells as $key => &$cell)
        {
            for ($i = $last + 1; $i < $key; $i++)
                echo '&nbsp;';
            $cell->print_cell();
            $last = $key;
        }
        echo '<br>';
    }
}


/** Represents a text block made up of a number of lines. */
class TextBlock
{
    var $rows = array();

    function get_row($r)
    {
        if (!array_key_exists($r, $this->rows))
            $this->rows[$r] = new Row();
        return $this->rows[$r];
    }

    function set_style($row, $col, $foreground, $background, $blinking)
    {
        $this->get_row($row)->set_style($col, $foreground, $background,
                                        $blinking);
    }

    function set_data($row, $col, $data)
    {
        $this->get_row($row)->set_data($col, $data);
    }

    function clear()
    {
        $this->rows = array();
    }

    function print_block()
    {
        echo '<span>';
        $last = 0;
        foreach ($this->rows as $key => &$row)
        {
            for ($i = $last + 1; $i < $key; $i++)
                echo '<br>';
            $row->print_row();
            $last = $key;
        }
        echo '<br></span>';
    }
}


/** Translates ANSI codes into a TextBlock. */
class AnsiTranslator
{
    private $ansi_colours = array( '0' => array (
                                       '30' => 'black',
                                       '31' => 'maroon',
                                       '32' => 'green',
                                       '33' => 'olive',
                                       '34' => 'navy',
                                       '35' => 'purple',
                                       '36' => 'teal',
                                       '37' => 'gray', ),
                                   '1' => array (
                                       '30' => 'silver',
                                       '31' => 'red',
                                       '32' => 'lime',
                                       '33' => 'yellow',
                                       '34' => 'blue',
                                       '35' => 'fuchsia',
                                       '36' => 'aqua',
                                       '37' => 'white' ) );

    private $lord_colours = array( '1' => 'navy',
                                   '2' => 'green',
                                   '3' => 'teal',
                                   '4' => 'maroon',
                                   '5' => 'purple',
                                   '6' => 'olive',
                                   '7' => 'gray',
                                   '8' => 'gray',
                                   '9' => 'blue',
                                   '0' => 'lime',
                                   '!' => 'aqua',
                                   '@' => 'red',
                                   '#' => 'fuchsia',
                                   '$' => 'yellow',
                                   '%' => 'white' );

    var $block;
    var $filename;

    /** Interpret special Legend of the Red Dragon colour codes?
     * These are of the style "`<code>" where <code> is 1-0 and shift-1 to
     * shift-5 (!-%).  If this is false the characters will be printed
     * literally.
     */
    var $interpret_lord_chars;

    function __construct($_filename, $_interpret_lord_chars)
    {
        $this->filename = $_filename;
        $this->interpret_lord_chars = $_interpret_lord_chars;
    }

    function translate()
    {
        $lines = file($this->filename);
        $this->block = new TextBlock();

        foreach ($lines as $line_num => $line)
            $this->translate_line($line_num, $line);

        return $this->block;
    }

    private function translate_line($line_num, $line)
    {
        $ansi_mode = false;
        $lord_colour_mode = false;
        $col = 1;
        for ($x = 0; $x < strlen($line); $x++)
        {
            $letter = substr($line, $x, 1);
            if ($lord_colour_mode)
            {
                // Next letter is a LORD colour indicator.
                $this->block->set_style($line_num+1, $col,
                                        $this->lord_colours[$letter], '',
                                        false);
                $lord_colour_mode = false;
            }
            else if (!$ansi_mode)
            {
                if ($letter == chr(0x1b))
                {
                    $ansi_mode = true;
                    $ansi_code = '';
                }
                else if ($this->interpret_lord_chars && $letter == '`')
                {
                    $lord_colour_mode = true;
                }
                else
                {
                    if ($letter == chr(0xc4))
                        $data = '&ndash;';
                    else
                        $data = htmlentities($letter);
                    $this->block->set_data($line_num + 1, $col, $data);
                    $col++;
                }
            }
            else
            {
                // We are in the middle of an ANSI code.
                if ($letter == 'm')
                {
                    if ($ansi_code == '0')
                        $this->block->set_style($line_num+1, $col, 'white',
                                                '', false);
                    else
                    {
                        $attrs = explode(';', $ansi_code);
                        $highlight = 0;
                        $colour = 30;
                        $background = '';
                        $blinking = false;

                        foreach ($attrs as $a)
                        {
                            if ($a == 0 || $a == 1)
                                $highlight = $a;
                            elseif ($a == 5 || $a == 6)
                                $blinking = true;
                            elseif ($a >= 30 && $a <= 37)
                                $colour = $a;
                            elseif ($a >= 40 && $a <= 47)
                                $background = $this->ansi_colours[0][$a - 10];
                        }

                        $this->block->set_style($line_num+1, $col, 
                                                $this->ansi_colours[$highlight]
                                                                   [$colour],
                                                $background,
                                                $blinking);
                    }
                    $ansi_mode = false;
                }
                else if ($letter == 'J')
                {
                    if ($ansi_code == '2')
                        $this->block->clear();
                    $ansi_mode = false;
                }
                else if ($letter != '[')
                    $ansi_code .= $letter;
            }
        }
    }
}

?>
</head>

<body bgcolor="black" color="white">
<tt>
<?php

$file = $_REQUEST['file'];
$lord = false;
if ($_REQUEST['lord'] == '1')
    $lord = true;

$translator = new AnsiTranslator("/var/www/$file", $lord);

$block = $translator->translate();

$block->print_block();
?>
</tt>
</body>
</html>

