<?php

namespace App\Services\MonthlyReport\Charts;

/**
 * Minimal SVG document builder. Every attribute/text value is escaped via
 * htmlspecialchars(..., ENT_XML1 | ENT_QUOTES) so callers never need to
 * think about XML-escaping themselves.
 */
class SvgCanvas
{
    /** @var string[] */
    private array $elements = [];

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES);
    }

    /**
     * Locale-independent numeric formatting for coordinates and lengths.
     * `%F` (capital) is unaffected by LC_NUMERIC, unlike `%f` or an implicit
     * (string) cast of a float, both of which can render a comma decimal
     * separator under locales such as de_DE.
     */
    public static function num(float $v): string
    {
        return sprintf('%.2F', $v);
    }

    /**
     * @param  array<string,string|int|float>  $attrs
     */
    private static function attrsToString(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $key => $value) {
            $str = is_int($value) || is_float($value) ? self::num((float) $value) : (string) $value;
            $out .= sprintf(' %s="%s"', self::esc((string) $key), self::esc($str));
        }

        return $out;
    }

    public function rect(float $x, float $y, float $w, float $h, array $attrs = []): static
    {
        $attrs = array_merge(['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h], $attrs);
        $this->elements[] = '<rect'.self::attrsToString($attrs).'/>';

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $attrs = []): static
    {
        $attrs = array_merge(['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2], $attrs);
        $this->elements[] = '<line'.self::attrsToString($attrs).'/>';

        return $this;
    }

    /**
     * @param  array<int,array{0:float,1:float}>  $points
     */
    public function polyline(array $points, string $stroke, float $width, ?string $dash = null): static
    {
        $pointsStr = implode(' ', array_map(
            static fn (array $p) => self::num((float) $p[0]).','.self::num((float) $p[1]),
            $points
        ));

        $attrs = [
            'points' => $pointsStr,
            'fill' => 'none',
            'stroke' => $stroke,
            'stroke-width' => $width,
        ];

        if ($dash !== null) {
            $attrs['stroke-dasharray'] = $dash;
        }

        $this->elements[] = '<polyline'.self::attrsToString($attrs).'/>';

        return $this;
    }

    public function circle(float $cx, float $cy, float $r, array $attrs = []): static
    {
        $attrs = array_merge(['cx' => $cx, 'cy' => $cy, 'r' => $r], $attrs);
        $this->elements[] = '<circle'.self::attrsToString($attrs).'/>';

        return $this;
    }

    /**
     * @param  array<string,string|int|float>  $attrs
     */
    public function text(float $x, float $y, string $s, array $attrs = []): static
    {
        $attrs = array_merge(['x' => $x, 'y' => $y], $attrs);
        $this->elements[] = '<text'.self::attrsToString($attrs).'>'.self::esc($s).'</text>';

        return $this;
    }

    /**
     * @param  array<string,string|int|float>  $attrs
     */
    public function group(string $inner, array $attrs = []): static
    {
        $this->elements[] = '<g'.self::attrsToString($attrs).'>'.$inner.'</g>';

        return $this;
    }

    public function toString(int $w, int $h): string
    {
        $body = implode('', $this->elements);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s</svg>',
            $w,
            $h,
            $w,
            $h,
            $body
        );
    }
}
