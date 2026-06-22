package com.dualfroz.ghstats.collector;

import com.dualfroz.ghstats.common.model.Streak;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Set;
import org.springframework.stereotype.Component;

@Component
public class StreakCalculator {

    public Streak compute(Set<LocalDate> activeDates, LocalDate today) {
        if (activeDates.isEmpty()) {
            return new Streak(0, 0, null, null);
        }

        List<LocalDate> sorted = new ArrayList<>(activeDates);
        Collections.sort(sorted);

        int longest = longestRun(sorted);

        LocalDate anchor = activeDates.contains(today) ? today
                : activeDates.contains(today.minusDays(1)) ? today.minusDays(1)
                : null;

        if (anchor == null) {
            return new Streak(0, longest, null, null);
        }

        LocalDate start = anchor;
        while (activeDates.contains(start.minusDays(1))) {
            start = start.minusDays(1);
        }
        int current = (int) (anchor.toEpochDay() - start.toEpochDay()) + 1;
        return new Streak(current, longest, start, anchor);
    }

    private int longestRun(List<LocalDate> sorted) {
        int longest = 1;
        int run = 1;
        for (int i = 1; i < sorted.size(); i++) {
            if (sorted.get(i).minusDays(1).equals(sorted.get(i - 1))) {
                run++;
            } else {
                run = 1;
            }
            longest = Math.max(longest, run);
        }
        return longest;
    }
}
