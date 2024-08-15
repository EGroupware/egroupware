import {customElement} from "lit/decorators/custom-element.js";
import {SlMenuItem} from "@shoelace-style/shoelace";
import {Et2Widget} from "../Et2Widget/Et2Widget";

@customElement('et2-menu-item')
export class Et2MenuItem extends Et2Widget(SlMenuItem)
{
}