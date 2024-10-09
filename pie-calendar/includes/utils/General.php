<?php

namespace Piecal\Utils;

Class General {

    /**
     * Formats and returns a string representation of the given value, encapsulated within a styled HTML block.
     * 
     * This method captures output buffering to construct an HTML string that represents the given value,
     * along with some contextual information, formatted for easier readability.
     * 
     * @param mixed $value The value to be formatted and displayed.
     * @param string $context A string representing the context in which this method is called.
     */
    public static function pretty( $value, $context = 'pretty() util function' ) {
        ?>
        <div style="padding: 32px; background: #EFEFEF; border-radius: 8px">
            <p>Data from <?php echo $context; ?></p>
            <hr>
            <pre style="max-height: 100vh; overflow: auto">
                <?php var_dump( $value ); ?>
            </pre>
        </div>
        <?php
    }

    /**
     * Deduplicates an array of associative arrays based on a unique identifier.
     * The unique identifier is created from the 'title', 'start', and 'end' values of each sub-array, 
     * after removing spaces, hyphens, and colons, and converting the characters to lowercase.
     *
     * This method will preserve the original array's keys.
     *
     * @param array $array The array of associative arrays to deduplicate. Each sub-array should have at least the keys 'title', 'start', and 'end'.
     * 
     * @return array The deduplicated array, with re-indexed numeric keys.
     *
     */
    public static function deduplicateArray( $array ) {
        $index = [];

        foreach( $array as $key => $value ) {
            $id = strtolower( str_replace( [' ', '-', ':'], '', $value['title'].$value['start'].$value['postId'] ) );

            if( in_array( $id, $index ) ) {
                unset( $array[$key] );
            } else {
                $index = [...$index, $id];
            }
        }

        return array_values( $array );
    }

    public static function filterArrayByAllowlist( $array, $allowlist ) {
        if( !isset( $array ) )
            return null;

        if (!is_array($array) && strpos($array, ',') !== false) {
            $array = array_map('trim', explode(',', $array));
        } else if( !is_array($array) && strpos($array, ',') === false ) {
            $array = [$array];
        }

        if( !isset( $allowlist ) )
            return $array;

        $array = array_intersect( $allowlist, $array );

        return $array;
    }

    public static function foundInArray( $needleArray, $haystackArray ) {
        $needleArray = array_map('strtolower', $needleArray);
        $haystackArray = array_map('strtolower', $haystackArray);

        return count(array_intersect($needleArray, $haystackArray)) > 0;
    }
}