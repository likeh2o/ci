<?php
/**
 * Script para la generación de CAPTCHAS
 *
 * @author  Jose Rodriguez <jose.rodriguez@exec.cl>
 * @license GPLv3
 * @link    http://code.google.com/p/cool-php-captcha
 * @package captcha
 *
 */

/**
 * SimpleCaptcha class
 *
 */
define('FONTPATH', dirname(__FILE__).'/fonts');

class SimpleCaptcha
{

    /** text show info 0:文本 1:数字 2:both*/
    public $content = 0;

    /** Width of the image */
    public $width  = 150;

    /** Height of the image */
    public $height = 50;

    /** Dictionary word file (empty for randnom text) */
   // public $wordsFile = 'words/en.txt';

    /** Min word length (for non-dictionary random text generation) */
    public $minWordLength = 4;

    /**
     * Max word length (for non-dictionary random text generation)
     *
     * Used for dictionary words indicating the word-length
     * for font-size modification purposes
     */
    public $maxWordLength = 4;

    /** Sessionname to store the original text */
   // public $session_var = 'captcha';

    /** Background color in RGB-array */
    public $backgroundColor = array(255, 255, 255);

    /** Foreground colors in RGB-array */
    public $colors = array(
        array(27,78,181), // blue
        array(22,163,35), // green
        array(214,36,7),  // red
    );

    /** Shadow color in RGB-array or false */
    public $shadowColor = false; //array(0, 0, 0);

    /** Horizontal line through the text */
    public $lineWidth = 0;
    
    /**
     * Font configuration
     *
     * - font: TTF file
     * - spacing: relative pixel space between character
     * - minSize: min font size
     * - maxSize: max font size
     */
    public $fonts = array(
        'Antykwa'  => array('spacing' => 1.5, 'minSize' => 20, 'maxSize' => 22, 'font' => 'AntykwaBold.ttf'),
        'Duality'  => array('spacing' => 1.5, 'minSize' => 20, 'maxSize' => 22, 'font' => 'Duality.ttf'),
        'Jura'     => array('spacing' => 1.5, 'minSize' => 20, 'maxSize' => 22, 'font' => 'Jura.ttf'),
        //'StayPuft' => array('spacing' => 1.5,'minSize' => 20, 'maxSize' => 20, 'font' => 'StayPuft.ttf'),
    );

    /** Wave configuracion in X and Y axes */
    public $Yperiod    = 12;
    public $Yamplitude = 7; //6;
    public $Xperiod    = 11;
    public $Xamplitude = 5; //4

    /** letter rotation clockwise */
    public $maxRotation = 8;
    public $text = "";
    public $image_path = "./image";

    /**
     * Internal image size factor (for better image quality)
     * 1: low, 2: medium, 3: high
     */
    public $scale = 3;

    /**
     * Blur effect for better image quality (but slower image processing).
     * Better image results with scale=3
     */
    public $blur = true;

    public $emboss = false;

    public $colorbg = false;

    /** Debug? */
    public $debug = false;
    
    /** Image format: jpeg or png */
    public $imageFormat = 'png';


    /** GD image */
    public $im;


    public function __construct($config = array())
    {
        $this->set_args($config);
    }


    private function set_args(array $params)
    {
        foreach ($params as $k => $v) {
            $this->$k = $v;
        }
    }


    public function CreateImage()
    {
        $ini = microtime(true);

        /** Initialization */
        $this->ImageAllocate();

        if ($this->colorbg) {
            $this->addBackground();
        }
        
        /** Text insertion */
        $text = $this->GetCaptchaText();
        $fontcfg  = $this->fonts[array_rand($this->fonts)];
        $this->WriteText($text, $fontcfg);
        
        /** Transformations */
        if (!empty($this->lineWidth)) {
            $this->WriteLine();
        }
        
        $this->WaveImage();
        if ($this->blur && function_exists('imagefilter')) {
            imagefilter($this->im, IMG_FILTER_GAUSSIAN_BLUR);
        }

        if ($this->emboss && function_exists('imagefilter')) {
            imagefilter($this->im, IMG_FILTER_EMBOSS);
        }
        
        $this->ReduceImage();

        if ($this->debug) {
            imagestring($this->im, 1, 1, $this->height-8,
                "$text {$fontcfg['font']} ".round((microtime(true)-$ini)*1000)."ms",
                $this->GdFgColor
            );
        }
    }

    public function ShowImage()
    {
        $this->CreateImage();
        imagecolortransparent($this->im, $this->GdBgColor);
        imagepng($this->im);
        $this->Cleanup();
    }


    /**
     * Creates the image resources
     */
    protected function ImageAllocate()
    {
        // Cleanup
        if (!empty($this->im)) {
            imagedestroy($this->im);
        }

        $this->im = imagecreatetruecolor($this->width*$this->scale, $this->height*$this->scale);

        // Background color
        $this->GdBgColor = imagecolorallocate($this->im,
            $this->backgroundColor[0],
            $this->backgroundColor[1],
            $this->backgroundColor[2]
        );
        
        //imagefill($this->im, 0, 0, $this->GdBgColor);
        imagefilledrectangle($this->im, 0, 0, $this->width*$this->scale, $this->height*$this->scale, $this->GdBgColor);
        
        // Foreground color
        $color           = $this->colors[mt_rand(0, sizeof($this->colors)-1)];
        $this->GdFgColor = imagecolorallocate($this->im, $color[0], $color[1], $color[2]);
        
        // line color
        $this->OpGdFgColor = imagecolorallocatealpha($this->im, 255-$color[0], 255-$color[1], 255-$color[2], 25);

        // Shadow color
        if (!empty($this->shadowColor) && is_array($this->shadowColor) && sizeof($this->shadowColor) >= 3) {
            $this->GdShadowColor = imagecolorallocate($this->im,
                $this->shadowColor[0],
                $this->shadowColor[1],
                $this->shadowColor[2]
            );
        }
    }


    /**
     * Text generation
     *
     * @return string Text
     */
    public function GetCaptchaText()
    {
        if (empty($this->text)) {
            $this->text = $this->GetRandomCaptchaText();
        }
        
        return $this->text;
    }


    /**
     * Random text generation
     *
     * @return string Text
     */
    protected function GetRandomCaptchaText($length = null)
    {
        if (empty($length)) {
            $length = rand($this->minWordLength, $this->maxWordLength);
        }

        $words = "abcdefghkmnpqrstuvwyz"; // remove i,j,l,o,x
        $vocals = "aeiou";
        // 数字和字符
        $words_number = '23456789'; // remove 0,1
        $len = 20;
        if ($this->content == 1) {
            $words = $words_number;
            $len = 7;
        } elseif ($this->content == 2) {
            $words .= $words_number;
            $len = 27;
        }

        $text  = "";
        for ($i=0; $i<$length; $i++) {
            $text .= substr($words, mt_rand(0, $len), 1);
        }
        
        return $text;
    }

    /**
     * Horizontal line insertion
     */
    protected function WriteLine()
    {
        $x1 = $this->width*$this->scale*.15;
        $x2 = $this->textFinalX;
        $y1 = rand($this->height*$this->scale*.40, $this->height*$this->scale*.65);
        $y2 = rand($this->height*$this->scale*.40, $this->height*$this->scale*.65);
        $width = $this->lineWidth/2*$this->scale;

        for ($i = $width*-1; $i <= $width; $i++) {
            imageline($this->im, $x1, $y1+$i, $x2, $y2+$i, $this->OpGdFgColor);
        }
    }

    /**
     * Text insertion
     */
    protected function WriteText($text, $fontcfg = array())
    {
        if (empty($fontcfg)) {
            // Select the font configuration
            $fontcfg  = $this->fonts[array_rand($this->fonts)];
        }

        $fontfile = FONTPATH . '/' . $fontcfg['font'];

        /** Increase font-size for shortest words: 9% for each glyp missing */
        $lettersMissing = $this->maxWordLength-strlen($text);
        $fontSizefactor = 1+($lettersMissing*0.09);

        // Text generation (char by char)
        $x      = 20*$this->scale;
        $y      = round(($this->height*27/40)*$this->scale);
        $length = strlen($text);
        for ($i=0; $i<$length; $i++) {
            $degree   = rand($this->maxRotation*-1, $this->maxRotation);
            $fontsize = rand($fontcfg['minSize'], $fontcfg['maxSize'])*$this->scale*$fontSizefactor;
            $letter   = substr($text, $i, 1);

            if ($this->shadowColor) {
                $coords = imagettftext($this->im, $fontsize, $degree,
                    $x+$this->scale+1, $y+$this->scale+1.5,
                    $this->GdShadowColor, $fontfile, $letter);
            }
            $coords = imagettftext($this->im, $fontsize, $degree,
                $x, $y,
                $this->GdFgColor, $fontfile, $letter);
            $x += ($coords[2]-$x) + ($fontcfg['spacing']*$this->scale);
        }

        $this->textFinalX = $x;
    }

    /**
     * Wave filter
     */
    protected function WaveImage()
    {
        // X-axis wave generation
        $xp = $this->scale*$this->Xperiod*rand(1, 3);
        $k = rand(0, 100);
        for ($i = 0; $i < ($this->width*$this->scale); $i++) {
            imagecopy($this->im, $this->im,
                $i-1, sin($k+$i/$xp) * ($this->scale*$this->Xamplitude),
                $i, 0, 1, $this->height*$this->scale);
        }

        // Y-axis wave generation
        $k = rand(0, 100);
        $yp = $this->scale*$this->Yperiod*rand(1, 2);
        for ($i = 0; $i < ($this->height*$this->scale); $i++) {
            imagecopy($this->im, $this->im,
                sin($k+$i/$yp) * ($this->scale*$this->Yamplitude), $i-1,
                0, $i, $this->width*$this->scale, 1);
        }
    }

    protected function addBackground()
    {
        $positions = array(
            array(40, 140),   // l
            array(150, 240),  // m
            array(250, 300)   // r
        );

        shuffle($positions);
        
        $yellow_x      = mt_rand($positions[0][0], $positions[0][1]);
        $yellow_y      = mt_rand(60, 80);
        
        $red_x         = mt_rand($positions[1][0], $positions[1][1]);
        $red_y         = mt_rand(0, 140);
        
        $blue_x        = mt_rand($positions[2][0], $positions[2][1]);
        $blue_y        = mt_rand(20, 125);
        
        $red_radius    = mt_rand(120, 170);
        $yellow_radius = mt_rand(120, 170);
        $blue_radius   = mt_rand(120, 170);

        // allocate colors with alpha values
        $yellow = imagecolorallocatealpha($this->im, 255, 255, 0, 75);
        $red    = imagecolorallocatealpha($this->im, 235, 144, 50, 75);
        $blue   = imagecolorallocatealpha($this->im, 115, 202, 204, 75);

        // drawing 3 overlapped circle
        imagefilledellipse($this->im, $yellow_x, $yellow_y, $yellow_radius, $yellow_radius, $yellow);
        imagefilledellipse($this->im, $red_x, $red_y, $red_radius, $red_radius, $red);
        imagefilledellipse($this->im, $blue_x, $blue_y, $blue_radius, $blue_radius, $blue);
    }


    /**
     * Reduce the image to the final size
     */
    protected function ReduceImage()
    {
        // Reduzco el tamaño de la imagen
        $imResampled = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($imResampled, $this->im,
            0, 0, 0, 0,
            $this->width, $this->height,
            $this->width*$this->scale, $this->height*$this->scale
        );
        imagedestroy($this->im);
        $this->im = $imResampled;
    }


    /**
     * File generation
     */
    protected function WriteImage()
    {
        imagecolortransparent($this->im, $this->GdBgColor);
        /*
        //ob_clean();
        if ($this->imageFormat == 'png') {
            imagepng($this->im,  "{$this->image_path}/{$this->text}.png");
        } else {
            imagejpeg($this->im, "$this->image_path/{$this->text}.jpeg", 80);
        }
        */
    }

    /**
     * Cleanup
     */
    protected function Cleanup()
    {
        imagedestroy($this->im);
    }
}
