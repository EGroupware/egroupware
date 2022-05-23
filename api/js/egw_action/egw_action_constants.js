//State bitmask (only use powers of two for new states!)
export const EGW_AO_STATE_NORMAL = 0x00;
export const EGW_AO_STATE_SELECTED = 0x01;
export const EGW_AO_STATE_FOCUSED = 0x02;
export const EGW_AO_STATE_VISIBLE = 0x04;  //< Can only be set by the AOI, means that the object is attached to the DOM-Tree and visible

export const EGW_AO_EVENT_DRAG_OVER_ENTER = 0x00;
export const EGW_AO_EVENT_DRAG_OVER_LEAVE = 0x01;

// No shift key is pressed
export const EGW_AO_SHIFT_STATE_NONE = 0x00;
// A shift key, which allows multiselection is pressed (usually CTRL on a PC keyboard)
export const EGW_AO_SHIFT_STATE_MULTI = 0x01;
// A shift key is pressed, which forces blockwise selection (SHIFT on a PC keyboard)
export const EGW_AO_SHIFT_STATE_BLOCK = 0x02;

// If this flag is set, this object will not be returned as "focused". If this
// flag is not applied to container objects, it may lead to some strange behaviour.
export const EGW_AO_FLAG_IS_CONTAINER = 0x01;

// If this flag is set, the object will gets its focus when no other object is
// selected and e.g. a key is pressed.
export const EGW_AO_FLAG_DEFAULT_FOCUS = 0x02;
export const EGW_AI_DRAG = 0x0100; // Use the first byte as mask for event types - 01 is for events used with drag stuff
export const EGW_AI_DRAG_OUT = EGW_AI_DRAG | 0x01;
export const EGW_AI_DRAG_OVER = EGW_AI_DRAG | 0x02;
export const EGW_AI_DRAG_ENTER = EGW_AI_DRAG | 0x03;

export const EGW_AO_EXEC_SELECTED = 0;
export const EGW_AO_EXEC_THIS = 1;

/**
 * Define the key constants (IE doesn't support "const" keyword)
 */

export const EGW_KEY_BACKSPACE = 8;
export const EGW_KEY_TAB = 9;
export const EGW_KEY_ENTER = 13;
export const EGW_KEY_ESCAPE = 27;
export const EGW_KEY_DELETE = 46;

export const EGW_KEY_SPACE = 32;

export const EGW_KEY_PAGE_UP = 33;
export const EGW_KEY_PAGE_DOWN = 34;

export const EGW_KEY_ARROW_LEFT = 37;
export const EGW_KEY_ARROW_UP = 38;
export const EGW_KEY_ARROW_RIGHT = 39;
export const EGW_KEY_ARROW_DOWN = 40;

export const EGW_KEY_0 = 48;
export const EGW_KEY_1 = 49;
export const EGW_KEY_2 = 50;
export const EGW_KEY_3 = 51;
export const EGW_KEY_4 = 52;
export const EGW_KEY_5 = 53;
export const EGW_KEY_6 = 54;
export const EGW_KEY_7 = 55;
export const EGW_KEY_8 = 56;
export const EGW_KEY_9 = 57;

export const EGW_KEY_A = 65;
export const EGW_KEY_B = 66;
export const EGW_KEY_C = 67;
export const EGW_KEY_D = 68;
export const EGW_KEY_E = 69;
export const EGW_KEY_F = 70;
export const EGW_KEY_G = 71;
export const EGW_KEY_H = 72;
export const EGW_KEY_I = 73;
export const EGW_KEY_J = 74;
export const EGW_KEY_K = 75;
export const EGW_KEY_L = 76;
export const EGW_KEY_M = 77;
export const EGW_KEY_N = 78;
export const EGW_KEY_O = 79;
export const EGW_KEY_P = 80;
export const EGW_KEY_Q = 81;
export const EGW_KEY_R = 82;
export const EGW_KEY_S = 83;
export const EGW_KEY_T = 84;
export const EGW_KEY_U = 85;
export const EGW_KEY_V = 86;
export const EGW_KEY_W = 87;
export const EGW_KEY_X = 88;
export const EGW_KEY_Y = 89;
export const EGW_KEY_Z = 90;

export const EGW_KEY_MENU = 93;

export const EGW_KEY_F1 = 112;
export const EGW_KEY_F2 = 113;
export const EGW_KEY_F3 = 114;
export const EGW_KEY_F4 = 115;
export const EGW_KEY_F5 = 116;
export const EGW_KEY_F6 = 117;
export const EGW_KEY_F7 = 118;
export const EGW_KEY_F8 = 119;
export const EGW_KEY_F9 = 120;
export const EGW_KEY_F10 = 121;
export const EGW_KEY_F11 = 122;
export const EGW_KEY_F12 = 123;

export const EGW_VALID_KEYS = [
    8, 9, 13, 27, 46, 32, 33, 34, 37, 38, 39, 40, 48, 49, 50, 51, 52, 53, 54,
    55, 56, 57, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80,
    81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 93, 112, 113, 114, 115, 116, 117, 118,
    119, 120, 121, 122, 123
]

