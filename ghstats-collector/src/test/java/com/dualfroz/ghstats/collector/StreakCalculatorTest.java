package com.dualfroz.ghstats.collector;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;

import com.dualfroz.ghstats.common.model.Streak;
import java.time.LocalDate;
import java.util.HashSet;
import java.util.Set;
import org.junit.jupiter.api.Test;

class StreakCalculatorTest {

    private final StreakCalculator calculator = new StreakCalculator();

    @Test
    void currentStreakEndsToday() {
        LocalDate today = LocalDate.of(2026, 8, 25);
        Set<LocalDate> active = new HashSet<>();
        active.add(LocalDate.of(2026, 8, 23));
        active.add(LocalDate.of(2026, 8, 24));
        active.add(LocalDate.of(2026, 8, 25));
        for (int day = 1; day <= 10; day++) {
            active.add(LocalDate.of(2026, 8, day));
        }

        Streak streak = calculator.compute(active, today);

        assertEquals(3, streak.current());
        assertEquals(10, streak.longest());
        assertEquals(LocalDate.of(2026, 8, 23), streak.currentStart());
        assertEquals(LocalDate.of(2026, 8, 25), streak.currentEnd());
    }

    @Test
    void currentStreakCountsYesterdayWhenTodayInactive() {
        LocalDate today = LocalDate.of(2026, 8, 25);
        Set<LocalDate> active = new HashSet<>();
        active.add(LocalDate.of(2026, 8, 23));
        active.add(LocalDate.of(2026, 8, 24));

        Streak streak = calculator.compute(active, today);

        assertEquals(2, streak.current());
        assertEquals(LocalDate.of(2026, 8, 24), streak.currentEnd());
    }

    @Test
    void noCurrentStreakWhenGapExceedsOneDay() {
        LocalDate today = LocalDate.of(2026, 8, 25);
        Set<LocalDate> active = new HashSet<>();
        active.add(LocalDate.of(2026, 8, 20));
        active.add(LocalDate.of(2026, 8, 21));

        Streak streak = calculator.compute(active, today);

        assertEquals(0, streak.current());
        assertEquals(2, streak.longest());
        assertNull(streak.currentStart());
    }

    @Test
    void emptyHistoryYieldsZeroStreak() {
        Streak streak = calculator.compute(Set.of(), LocalDate.of(2026, 8, 25));

        assertEquals(0, streak.current());
        assertEquals(0, streak.longest());
        assertNull(streak.currentStart());
        assertNull(streak.currentEnd());
    }
}
