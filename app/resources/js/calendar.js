import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

/*
 * Time zone handling (verified offset strategy, no named-zone plugin):
 * The server sends business-local wall-clock strings without an offset (Asia/Ulaanbaatar, fixed +08:00,
 * no DST) and the calendar runs in timeZone 'UTC'. A viewer whose device is in Tokyo or UTC still sees
 * 10:00 as 10:00 Mongolia time. Values sent back are read with getUTC* and are again business-local.
 */
const mongolian = {
    code: 'mn',
    week: { dow: 1, doy: 4 },
    buttonText: { prev: 'Өмнөх', next: 'Дараах', today: 'Өнөөдөр', month: 'Сар', week: '7 хоног', day: 'Өдөр', list: 'Жагсаалт' },
    weekText: '7х',
    allDayText: 'Өдөржин',
    moreLinkText: (n) => `+${n} бусад`,
    noEventsText: 'Энэ хугацаанд цаг алга',
};

const pad = (n) => String(n).padStart(2, '0');
const weekdays = ['Ням', 'Даваа', 'Мягмар', 'Лхагва', 'Пүрэв', 'Баасан', 'Бямба'];
const months = ['1-р сар', '2-р сар', '3-р сар', '4-р сар', '5-р сар', '6-р сар', '7-р сар', '8-р сар', '9-р сар', '10-р сар', '11-р сар', '12-р сар'];

// Browsers often ship no Mongolian Intl data, so all calendar text is formatted explicitly here.
const marker = (arg) => (arg.date && arg.date.marker ? arg.date.marker : arg.date);
const ymd = (d) => `${d.getUTCFullYear()}.${pad(d.getUTCMonth() + 1)}.${pad(d.getUTCDate())}`;
const formatters = {
    titleFormat: (arg) => {
        const start = arg.start.marker;
        const end = arg.end ? new Date(arg.end.marker.getTime() - 1) : start;
        if (start.getUTCMonth() === end.getUTCMonth() && start.getUTCDate() === 1 && end.getUTCDate() >= 28) {
            return `${start.getUTCFullYear()} оны ${months[start.getUTCMonth()]}`;
        }
        return start.toDateString() === end.toDateString() ? `${ymd(start)}, ${weekdays[start.getUTCDay()]}` : `${ymd(start)} – ${ymd(end)}`;
    },
    dayHeaderFormat: (arg) => {
        const d = marker(arg);
        return `${weekdays[d.getUTCDay()].slice(0, 2)} ${pad(d.getUTCMonth() + 1)}/${pad(d.getUTCDate())}`;
    },
    listDayFormat: (arg) => {
        const d = marker(arg);
        return `${weekdays[d.getUTCDay()]}, ${months[d.getUTCMonth()]} ${d.getUTCDate()}`;
    },
    listDaySideFormat: false,
};
const wallClock = (date) =>
    `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:00`;

function angaaCalendar() {
    return {
        calendar: null,
        init() {
            const wire = this.$wire;
            const mobile = window.matchMedia('(max-width: 767px)').matches;

            this.calendar = new Calendar(this.$refs.calendar, {
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                locale: mongolian,
                ...formatters,
                timeZone: 'UTC',
                firstDay: 1,
                initialView: mobile ? 'listWeek' : 'timeGridWeek',
                headerToolbar: mobile
                    ? { left: 'prev,next', center: 'title', right: 'today' }
                    : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                footerToolbar: mobile ? { center: 'listWeek,timeGridDay,dayGridMonth' } : false,
                slotMinTime: '07:00:00',
                slotMaxTime: '21:00:00',
                slotDuration: '00:30:00',
                nowIndicator: true,
                allDaySlot: false,
                height: 'auto',
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                editable: true,
                eventDurationEditable: true,
                events: async (info, success, failure) => {
                    try {
                        success(await wire.events(wallClock(info.start), wallClock(info.end)));
                    } catch (error) {
                        failure(error);
                    }
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    wire.openAppointment(info.event.id);
                },
                eventDrop: (info) => this.move(info),
                eventResize: (info) => this.move(info),
                eventAllow: (span, event) => event.extendedProps.movable === true,
                eventDidMount: (info) => {
                    const props = info.event.extendedProps;
                    info.el.setAttribute('aria-label', `${info.event.title}. ${props.statusLabel ?? ''}`);
                    info.el.title = props.tooltip ?? info.event.title;
                },
            });

            this.calendar.render();
            this.$wire.$on('calendar-refresh', () => this.calendar.refetchEvents());
        },
        async move(info) {
            const label = `${info.event.title}\nШинэ цаг: ${wallClock(info.event.start).replace('T', ' ').slice(0, 16)} – ${wallClock(info.event.end).slice(11, 16)}`;

            if (!window.confirm(`Цагийг өөрчлөх үү?\n${label}`)) {
                info.revert();
                return;
            }

            const ok = await this.$wire.moveAppointment(
                info.event.id,
                wallClock(info.event.start),
                wallClock(info.event.end),
                info.event.extendedProps.version,
            );

            if (!ok) {
                info.revert();
            }

            this.calendar.refetchEvents();
        },
    };
};

const register = () => window.Alpine.data('angaaCalendar', angaaCalendar);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
