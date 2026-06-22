package com.dualfroz.ghstats.server.render;

import java.text.NumberFormat;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

public final class Format {

    private static final DateTimeFormatter DAY = DateTimeFormatter.ofPattern("MMM d, yyyy", Locale.ENGLISH);
    private static final DateTimeFormatter DAY_SHORT = DateTimeFormatter.ofPattern("MMM d", Locale.ENGLISH);

    private Format() {
    }

    public static String grouped(long value) {
        return NumberFormat.getIntegerInstance(Locale.US).format(value);
    }

    public static String percent(double share) {
        return String.format(Locale.US, "%.1f%%", share * 100.0);
    }

    public static String day(LocalDate date) {
        return date == null ? "" : DAY.format(date);
    }

    public static String range(LocalDate start, LocalDate end) {
        if (start == null || end == null) {
            return "-";
        }
        if (start.equals(end)) {
            return DAY.format(start);
        }
        if (start.getYear() == end.getYear()) {
            return DAY_SHORT.format(start) + " - " + DAY.format(end);
        }
        return DAY.format(start) + " - " + DAY.format(end);
    }
}
