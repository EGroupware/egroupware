/*
    DynAPI Distribution
    SnapX Class by Leif Westerlind <warp-9.9 (at) usa (dot) net>

    The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

    requires: DynLayer
*/

SnapX = {}; // used by dynapi.library

var p = DynLayer.prototype;
p._snapSetLocation = p.setLocation;
p.setLocation  = function(x,y){	
    this._snapSetLocation(x,y);

    if (this.isSnapEnabled){
        var dirX = '', dirY = '';

        // get direction
        if (this._snapX != this.x){
            if (this._snapX < this.x){
                dirX="E";
            }
            else {
                dirX="W";
            }
        }

        if (this._snapY != this.y){
            if (this._snapY < this.y){
                dirY="S";
            }
            else {
                dirY="N";
            }
        }

        this._snapX = this.x;
        this._snapY = this.y;
        this._snapDirection = dirY + dirX;
        this._checkForSnap();
    }
};

p.getSnapDirection = function (){
    return(this._snapDirection);
};

/*
INPUT:

    0 args: use defaults
    1 arg : snap type [normal|null|sticky|grid]
    2 args: snap type [normal|null|sticky|grid],
            boundary type [inner|outer|both]
    3 args: snap type [normal|null|sticky|grid],
            boundary type [inner|outer|both],
            boundary
    4 args: snap type [normal|null|sticky|grid],
            boundary type [inner|outer|both],
            boundary,
            grid size (if applicable)
*/
p.enableSnap = function (){
    var a = arguments;
    var snapBoundaryDefault = DynLayer._snapBoundaryDefault;

    this.isSnapEnabled = true;
    this._snapX = this.x;
    this._snapY = this.y;
    
    if ( a.length >= 0 ){
        this.setSnapBoundary();
        this.setSnapBoundaryType();
    }

    if ( a.length >= 1 ){
        if ( a[0] == 'sticky' || a[0] == 'grid' ){
            this.setSnapType(a[0]);
        }
        else if ( a[0] == 'normal' || a[0] == null ){
            this.setSnapType();
        }
    }

    if ( a.length >= 2 ){
        if ( a[1] == 'inner' || a[1] == 'outer' || a[1] == 'both' ){
            this.setSnapBoundaryType(a[1]);
        }
        else {
            this.setSnapBoundaryType();
        }
    }

    if ( a.length >= 3 ){
        if ( typeof(a[2]) == 'number' ){
            this.setSnapBoundary(a[1],a[2]);
        }
        else {
            this.setSnapBoundary(a[1],snapBoundaryDefault);
        }
    }
    
    if ( a.length >= 4 ){
        if ( typeof(a[3]) == 'number' ){
            this.setGridSnapSize(a[3]);
        }
        else {
            this.setGridSnapSize();
        }
    }
};
p.disableSnap = function(){
    this.isSnapEnabled = false;
};

p.setSnapType = function(t){
    var a = arguments;
    
    if (a.length == 0){
        this._snapType = 'normal';
    }
    else if (a.length == 1 ){
        if ( a[0] == 'sticky' ){
            this._snapType = a[0];
            this.enableStickySnap();
        } else if ( a[0] == 'grid' ){
            this._snapType = a[0];
            this.enableGridSnap();
        }
        else {
            this._snapType = 'normal';
        }
    }
}

p.enableStickySnap = function(){
    if (arguments.length == 0){
        this.isStickySnapEnabled = true;
    }
    else if (arguments.length == 1 ){
        if (arguments[0] === true){
            this.isStickySnapEnabled = true;
        }
        else if (arguments[0] === false){
            this.isStickySnapEnabled = false;
        }
    }
};
p.disableStickySnap = function(){
    this.isStickySnapEnabled = false;
};

p.enableGridSnap = function(){
    this.isGridSnapEnabled = true;
    this.setGridSnapSize();
    this._snapGridX = null;
    this._snapGridY = null;
};
p.disableGridSnap = function(){
    this.isGridSnapEnabled = false;
};

DynLayer._snapBoundaryTypeDefault = 'outer';
p.setSnapBoundaryTypeDefault = function(snapBoundaryType){
    if (typeof(snapBoundaryType) == 'string') DynLayer._snapBoundaryTypeDefault = snapBoundaryType;
}
p.getSnapBoundaryTypeDefault = function(){
    return(DynLayer._snapBoundaryTypeDefault);
}
p.setSnapBoundaryType = function(snapBoundaryType){
    if (arguments.length == 0){
        this._snapBoundaryType = DynLayer._snapBoundaryTypeDefault;
    }
    else if (typeof(snapBoundaryType) == 'string'){
        this._snapBoundaryType = snapBoundaryType;
    }
};
p.getSnapBoundaryType = function(){
    return(this._snapBoundaryType);
};

DynLayer._snapBoundaryDefault = 25;
p.setSnapBoundaryDefault = function(snapBoundary){
    if(typeof(snapBoundary) == 'number') DynLayer._snapBoundaryDefault = snapBoundary;
}
p.getSnapBoundaryDefault = function(){
    return(DynLayer._snapBoundaryDefault);
}

DynLayer._snapGridSizeDefault = 10;
p.setGridSnapSizeDefault = function(s){
    DynLayer._snapGridSizeDefault = s;
}
p.getGridSnapSizeDefault = function(){
    return(DynLayer._snapGridSizeDefault);
}
p.setGridSnapSize = function(){
    if (arguments.length == 0){
        this._snapGridSize = DynLayer._snapGridSizeDefault;
    }
    else if (arguments.length == 1 ){
        this._snapGridSize = arguments[0];
    }
};

/*
   INPUT:
       0 args: set snapBoundaryType to snapBoundaryTypeDefault,
               set boundary to snapBoundaryDefault
       1 arg : if snapBoundaryType, set boundary to snapBoundaryDefault,
               if boundary, set all sides to boundary
                   and snapBoundaryType to snapBoundaryTypeDefault
       2 args: if snapBoundaryType,boundary, set type and boundary for all sides
               if N1,N2 set inner to N1 and outer to N2 and
                   set snapBoundaryType to both
       5 args: snapBoundaryType, t, r, b, l
       8 args: ti, ri, bi, li, to, ro, bo, lo
*/

p.setSnapBoundary = function(){
    var a = arguments;
    var snapBoundaryDefault = DynLayer._snapBoundaryDefault;
    
    if (a.length == 0){
        this.setSnapBoundaryType();
        this._snapBndTi = snapBoundaryDefault;
        this._snapBndRi = snapBoundaryDefault;
        this._snapBndBi = snapBoundaryDefault;
        this._snapBndLi = snapBoundaryDefault;
        this._snapBndTo = snapBoundaryDefault;
        this._snapBndRo = snapBoundaryDefault;
        this._snapBndBo = snapBoundaryDefault;
        this._snapBndLo = snapBoundaryDefault;
    }
    if (a.length == 1){
        if(a[0] == 'inner' || a[0] == 'outer' || a[0] == 'both'){
            this.setSnapBoundaryType(a[0]);
            this._snapBndTi = snapBoundaryDefault;
            this._snapBndRi = snapBoundaryDefault;
            this._snapBndBi = snapBoundaryDefault;
            this._snapBndLi = snapBoundaryDefault;
            this._snapBndTo = snapBoundaryDefault;
            this._snapBndRo = snapBoundaryDefault;
            this._snapBndBo = snapBoundaryDefault;
            this._snapBndLo = snapBoundaryDefault;
        }
        else {
            this.setSnapBoundaryType();
            this._snapBndTi = a[0];
            this._snapBndRi = a[0];
            this._snapBndBi = a[0];
            this._snapBndLi = a[0];
            this._snapBndTo = a[0];
            this._snapBndRo = a[0];
            this._snapBndBo = a[0];
            this._snapBndLo = a[0];
        }
    }
    else if (a.length == 2){
        if (a[0] == 'inner'){
            this.setSnapBoundaryType(a[0]);
            this._snapBndTi = a[1];
            this._snapBndRi = a[1];
            this._snapBndBi = a[1];
            this._snapBndLi = a[1];
        }
        else if (a[0] == 'outer'){
            this.setSnapBoundaryType(a[0]);
            this._snapBndTo = a[1];
            this._snapBndRo = a[1];
            this._snapBndBo = a[1];
            this._snapBndLo = a[1];
        }
        else if (a[0] == 'both' || a[0] == null){
            this.setSnapBoundaryType('both');
            this._snapBndTi = a[1];
            this._snapBndRi = a[1];
            this._snapBndBi = a[1];
            this._snapBndLi = a[1];
            this._snapBndTo = a[1];
            this._snapBndRo = a[1];
            this._snapBndBo = a[1];
            this._snapBndLo = a[1];
        }
        else if (typeof(a[0]) == 'number' && typeof(a[1]) == 'number'){
            this.setSnapBoundaryType('both');
            this._snapBndTi = a[0];
            this._snapBndRi = a[0];
            this._snapBndBi = a[0];
            this._snapBndLi = a[0];
            this._snapBndTo = a[1];
            this._snapBndRo = a[1];
            this._snapBndBo = a[1];
            this._snapBndLo = a[1];
        }
    }
    else if (a.length == 5){
        if(a[0] == 'inner' || a[0] == 'outer' || a[0] == 'both'){
            this.setSnapBoundaryType(a[0]);
        }

        if (this._snapBoundaryType == 'inner'){
            this._snapBndTi = a[1];
            this._snapBndRi = a[2];
            this._snapBndBi = a[3];
            this._snapBndLi = a[4];
        }
        else if (this._snapBoundaryType == 'outer'){
            this._snapBndTo = a[1];
            this._snapBndRo = a[2];
            this._snapBndBo = a[3];
            this._snapBndLo = a[4];
        }
        else if (this._snapBoundaryType == 'both'){
            this._snapBndTi = a[1];
            this._snapBndRi = a[2];
            this._snapBndBi = a[3];
            this._snapBndLi = a[4];
            this._snapBndTo = a[1];
            this._snapBndRo = a[2];
            this._snapBndBo = a[3];
            this._snapBndLo = a[4];
        }
    }
    else if (a.length == 8){
        this.setSnapBoundaryType('both');
        this._snapBndTi = a[0];
        this._snapBndRi = a[1];
        this._snapBndBi = a[2];
        this._snapBndLi = a[3];
        this._snapBndTo = a[4];
        this._snapBndRo = a[5];
        this._snapBndBo = a[6];
        this._snapBndLo = a[7];
    }
    else {
        this.setSnapBoundaryType();
        this._snapBndTi = snapBoundaryDefault;
        this._snapBndRi = snapBoundaryDefault;
        this._snapBndBi = snapBoundaryDefault;
        this._snapBndLi = snapBoundaryDefault;
        this._snapBndTo = snapBoundaryDefault;
        this._snapBndRo = snapBoundaryDefault;
        this._snapBndBo = snapBoundaryDefault;
        this._snapBndLo = snapBoundaryDefault;
    }
};
p.getSnapBoundary = function(t){
    var To,Ro,Bo,Lo,Ti,Ri,Bi,Li,bndAry,X,Y,W,H;

    X = this.x;
    Y = this.y;
    W = this.w;
    H = this.h;

    Ti = Y + this._snapBndTi;
    Ri = X + W - this._snapBndRi;
    Bi = Y + H - this._snapBndBi;
    Li = X + this._snapBndLi;

    To = Y - this._snapBndTo;
    Ro = X + W + this._snapBndRo;
    Bo = Y + H + this._snapBndBo;
    Lo = X - this._snapBndLo;

    if (t==null) bndAry = [Ti,Ri,Bi,Li,To,Ro,Bo,Lo];
    else {
        if (t=='inner') bndAry = [Ti,Ri,Bi,Li];
        else if (t=='outer') bndAry = [To,Ro,Bo,Lo];
        else if (t=='both') bndAry = [Ti,Ri,Bi,Li,To,Ro,Bo,Lo];
    }
    return(bndAry);
};

p._checkForSnap = function(){
    switch (this._snapBoundaryType) {
        case 'outer' :
            this._checkForSnapOuter();
            break;
        case 'inner' :
            this._checkForSnapInner();
            break;
        case 'both' :
            this._checkForSnapInner();
            this._checkForSnapOuter();
            break;
        default:
            return(false);
    }
};

p._checkForSnapInner = function(){
    if (!this.parent.children.length>0) return(false);
    if (!this.isSnapEnabled==true) return(false);

    var ch,chX1,chY1,chX2,chY2,
        chBiX1,chBiY1,chBiX2,chBiY2,
        sX1,sY1,sX2,sY2,
        chBndAry,sDir,
        B1,B2a,B2b,B3,B4a,B4b,B5,B6a,B6b,B7,B8a,B8b,
        D1,D2a,D2b,D3,D4a,D4b,D5,D6a,D6b,D7,D8a,D8b,
        C1,C2a,C2b,C3,C4a,C4b,C5,C6a,C6b,C7,C8a,C8b;

    sX1  = this.x;
    sY1  = this.y;
    sW   = this.w;
    sH   = this.h;
    sX2  = sX1 + sW;
    sY2  = sY1 + sH;
    sDir = this.getSnapDirection();

    for (var i in this.parent.children){
        ch = this.parent.children[i];
        if (ch != this && ch.isSnapEnabled == true){
            chX1 = ch.x;
            chY1 = ch.y;
            chX2 = chX1 + ch.w;
            chY2 = chY1 + ch.h;
            chBndAry = ch.getSnapBoundary('inner');
            chBiX1 = chBndAry[3];
            chBiY1 = chBndAry[0];
            chBiX2 = chBndAry[1];
            chBiY2 = chBndAry[2];

            // Cases B1 - B8 test TRUE if source corner is in snap border.
            // Cases D1 - D8 test TRUE if the corresponding direction of
            //               movement of the corner is towards the boundary.
            // Cases C1 - C8 test TRUE if the corresponding B and D cases
            //               are true for standard or sticky snap.

            // inner top-left corner, source top-left, move N, NW, W
            B1  = (sX1 <= chBiX1 && sX1 >  chX1   && sY1 <= chBiY1 && sY1 > chY1);
            D1  = (sDir == 'N' || sDir == 'NW' || sDir == 'W');
            C1  = (B1 && (D1 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner top-middle side, source top-left, move NE, N, NW
            B2a = (sX1 >  chBiX1 && sX1 <  chBiX2 && sY1 <= chBiY1 && sY1 > chY1);
            D2a = (sDir == 'NE' || sDir == 'N' || sDir == 'NW');
            C2a = (B2a && (D2a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner top-middle side, source top-right, move NE, N, NW
            B2b = (sX2 >  chBiX1 && sX2 <  chBiX2 && sY1 <= chBiY1 && sY1 > chY1);
            D2b = (sDir == 'NE' || sDir == 'N' || sDir == 'NW');
            C2b = (B2b && (D2b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner top-right corner, source top-right, move E, NE, N
            B3  = (sX2 >= chBiX2 && sX2 <  chX2   && sY1 <= chBiY1 && sY1 > chY1);
            D3  = (sDir == 'E' || sDir == 'NE' || sDir == 'N');
            C3  = (B3 && (D3 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner right-middle side, source top-right, move SE, E, NE
            B4a = (sX2 >= chBiX2 && sX2 <  chX2   && sY1 >  chBiY1 && sY1 < chBiY2);
            D4a = (sDir == 'SE' || sDir == 'E' || sDir == 'NE');
            C4a = (B4a && (D4a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner right-middle side, source bottom-right, move SE, E, NE
            B4b = (sX2 >= chBiX2 && sX2 <  chX2   && sY2 >  chBiY1 && sY2 < chBiY2);
            D4b = (sDir == 'SE' || sDir == 'E' || sDir == 'NE');
            C4b = (B4b && (D4b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner bottom-right corner, source lower-right, move dir E, SE, S
            B5  = (sX2 >= chBiX2 && sX2 <  chX2   && sY2 >= chBiY2 && sY2 < chY2);
            D5  = (sDir == 'E' || sDir == 'SE' || sDir == 'S');
            C5  = (B5 && (D5 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner bottom-middle side, source lower-left, move SW, S, SE
            B6a = (sX1 >  chBiX1 && sX1 <  chBiX2 && sY2 >= chBiY2 && sY2 < chY2);
            D6a = (sDir == 'SW' || sDir == 'S' || sDir == 'SE');
            C6a = (B6a && (D6a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner bottom-middle side, source lower-right, move SW, S, SE
            B6b = (sX2 >  chBiX1 && sX2 <  chBiX2 && sY2 >= chBiY2 && sY2 < chY2);
            D6b = (sDir == 'SW' || sDir == 'S' || sDir == 'SE');
            C6b = (B6b && (D6b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner bottom-left corner, source lower-left, move W, SW, S
            B7  = (sX1 <= chBiX1 && sX1 >  chX1   && sY2 >= chBiY2 && sY2 < chY2);
            D7  = (sDir == 'W' || sDir == 'SW' || sDir == 'S');
            C7  = (B7 && (D7 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner left-middle side, source top-left, move NW, W, SW
            B8a = (sX1 <= chBiX1 && sX1 >  chX1   && sY1 >  chBiY1 && sY1 < chBiY2);
            D8a = (sDir == 'NW' || sDir == 'W' || sDir == 'SW');
            C8a = (B8a && (D8a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // inner left-middle side, source bottom-left, move NW, W, SW
            B8b = (sX1 <= chBiX1 && sX1 >  chX1   && sY2 >  chBiY1 && sY2 < chBiY2);
            D8b = (sDir == 'NW' || sDir == 'W' || sDir == 'SW');
            C8b = (B8b && (D8b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            if (C1){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX1, chY1);
                }
            }
            else if (C3){
                if (this.isGridSnapEnabled){
                    var tmpX = chX2 - ( Math.floor( ( chX2 - sX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX2-sW, chY1);
                }
            }
            else if (C5){
                if (this.isGridSnapEnabled){
                    var tmpX = chX2 - ( Math.floor( ( chX2 - sX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY2 - sH - ( Math.floor( ( chY2 - sY2 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX2-sW, chY2-sH);
                }
            }
            else if (C7){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY2 - sH - ( Math.floor( ( chY2 - sY2 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX1, chY2-sH);
                }
            }
            else if (C2a || C2b){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, chY1);
                }
                else {
                    this._snapSetLocation(sX1, chY1);
                }
            }
            else if (C4a || C4b){
                if (this.isGridSnapEnabled){
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(chX2-sW, tmpY);
                }
                else {
                    this._snapSetLocation(chX2-sW, sY1);
                }
            }
            else if (C6a || C6b){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, chY2-sH);
                }
                else {
                    this._snapSetLocation(sX1, chY2-sH);
                }
            }
            else if (C8a || C8b){
                if (this.isGridSnapEnabled){
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(chX1 , tmpY);
                }
                else {
                    this._snapSetLocation(chX1 , sY1);
                }
            }

            if (C1 || C2a || C2b || C3 || C4a || C4b || C5 || C6a || C6b || C7 || C8a || C8b){
                this._snapObject=ch;
                this.invokeEvent("snap");
                ch._snapObject=this;
                ch.invokeEvent("snap");
            }
        }
    }
};

p._checkForSnapOuter = function(){
    if (! this.parent.children.length > 0) return(false);
    if (! this.isSnapEnabled == true) return(false);

    var ch,chX1,chY1,chX2,chY2,
        chBoX1,chBoY1,chBoX2,chBoY2,
        sX1,sY1,sX2,sY2,
        chBndAry,sDir,
        B1,B2a,B2b,B3,B4a,B4b,B5,B6a,B6b,B7,B8a,B8b,
        D1,D2a,D2b,D3,D4a,D4b,D5,D6a,D6b,D7,D8a,D8b,
        C1,C2a,C2b,C3,C4a,C4b,C5,C6a,C6b,C7,C8a,C8b;

/*
    if(this.isGridSnapEnabled){
        if( Math.abs( this._snapX - this._snapGridX ) > this._snapGridSize ) this._snapGridX=null;
        if( Math.abs( this._snapY - this._snapGridY ) > this._snapGridSize ) this._snapGridY=null;
        this._snapSetLocation( this._snapGridX || this._snapX, this._snapGridY || this._snapY );
    }
*/

    sX1 = this.x;
    sY1 = this.y;
    sW  = this.w;
    sH  = this.h;
    sX2 = sX1 + sW;
    sY2 = sY1 + sH;
    sDir = this.getSnapDirection();

    for (var i in this.parent.children){
        ch = this.parent.children[i];
        if (ch != this && ch.isSnapEnabled == true){
            chX1 = ch.x;
            chY1 = ch.y;
            chX2 = chX1 + ch.w;
            chY2 = chY1 + ch.h;
            chBndAry = ch.getSnapBoundary('outer');
            chBoX1 = chBndAry[3];
            chBoY1 = chBndAry[0];
            chBoX2 = chBndAry[1];
            chBoY2 = chBndAry[2];

            // Cases B1 - B8 test TRUE if source corner is in snap border.
            // Cases D1 - D8 test TRUE if the corresponding direction of
            //               movement of the corner is towards the boundary.
            // Cases C1 - C8 test TRUE if the corresponding B and D cases
            //               are true for standard, sticky or grid snap.

            // outer top-left corner, source lower-right, move dir E, SE, S
            B1  = (sX2 >= chBoX1 && sX2 <  chX1 && sY2 >= chBoY1 && sY2 <  chY1);
            D1  = (sDir == 'E' || sDir == 'SE' || sDir == 'S');
            C1  = (B1 && (D1 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer top-middle side, source lower-left, move SW, S, SE
            B2a = (sX1 >= chX1   && sX1 <= chX2 && sY2 >= chBoY1 && sY2 <  chY1);
            D2a = (sDir == 'SW' || sDir == 'S' || sDir == 'SE');
            C2a = (B2a && (D2a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer top-middle side, source lower-right, move SW, S, SE
            B2b = (sX2 >= chX1   && sX2 <= chX2 && sY2 >= chBoY1 && sY2 <  chY1);
            D2b = (sDir == 'SW' || sDir == 'S' || sDir == 'SE');
            C2b = (B2b && (D2b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer top-right corner, source lower-left, move W, SW, S
            B3  = (sX1 <= chBoX2 && sX1 >  chX2 && sY2 >= chBoY1 && sY2 <  chY1);
            D3  = (sDir == 'W' || sDir == 'SW' || sDir == 'S');
            C3  = (B3 && (D3 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));
            
            // outer right-middle side, source top-left, move NW, W, SW
            B4a = (sX1 <= chBoX2 && sX1 >  chX2 && sY1 >= chY1   && sY1 <= chY2);
            D4a = (sDir == 'NW' || sDir == 'W' || sDir == 'SW');
            C4a = (B4a && (D4a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer right-middle side, source bottom-left, move NW, W, SW
            B4b = (sX1 <= chBoX2 && sX1 >  chX2 && sY2 >= chY1   && sY2 <= chY2);
            D4b = (sDir == 'NW' || sDir == 'W' || sDir == 'SW');
            C4b = (B4b && (D4b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer bottom-right corner, source top-left, move N, NW, W
            B5  = (sX1 <= chBoX2 && sX1 >  chX2 && sY1 <= chBoY2 && sY1 >  chY2);
            D5  = (sDir == 'N' || sDir == 'NW' || sDir == 'W');
            C5  = (B5 && (D5 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer bottom-middle side, source top-left, move NE, N, NW
            B6a = (sX1 >= chX1   && sX1 <= chX2 && sY1 <= chBoY2 && sY1 >  chY2);
            D6a = (sDir == 'NE' || sDir == 'N' || sDir == 'NW');
            C6a = (B6a && (D6a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer bottom-middle side, source top-right, move NE, N, NW
            B6b = (sX2 >= chX1   && sX2 <= chX2 && sY1 <= chBoY2 && sY1 >  chY2);
            D6b = (sDir == 'NE' || sDir == 'N' || sDir == 'NW');
            C6b = (B6b && (D6b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer bottom-left corner, source top-right, move E, NE, N
            B7  = (sX2 >= chBoX1 && sX2 <  chX1 && sY1 <= chBoY2 && sY1 >  chY2);
            D7  = (sDir == 'E' || sDir == 'NE' || sDir == 'N');
            C7  = (B7 && (D7 || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer left-middle side, source top-right, move SE, E, NE
            B8a = (sX2 >= chBoX1 && sX2 <  chX1 && sY1 >= chY1  && sY1  <= chY2);
            D8a = (sDir == 'SE' || sDir == 'E' || sDir == 'NE');
            C8a = (B8a && (D8a || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            // outer left-middle side, source bottom-right, move SE, E, NE
            B8b = (sX2 >= chBoX1 && sX2 <  chX1 && sY2 >= chY1  && sY2  <= chY2);
            D8b = (sDir == 'SE' || sDir == 'E' || sDir == 'NE');
            C8b = (B8b && (D8b || ch.isStickySnapEnabled || ch.isGridSnapEnabled));

            if (C1){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 - ( Math.floor( ( chX1 - sX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 - ( Math.floor( ( chY1 - sY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX1-sW, chY1-sH);
                }
            }
            else if (C3){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 - ( Math.floor( ( chY1 - sY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX2, chY1-sH);
                }
            }
            else if (C5){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 + ( Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX2, chY2);
                }
            }
            else if (C7){
                if (this.isGridSnapEnabled){
                    var tmpX = chX1 - ( Math.floor( ( chX1 - sX1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    var tmpY = chY1 + ( Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) ) * this._snapGridSize;
                    this._snapSetLocation(tmpX, tmpY);
                }
                else {
                    this._snapSetLocation(chX1-sW, chY2);
                }
            }
            else if (C2a || C2b){
                if (this.isGridSnapEnabled){
                    //if(!this._snapGridY) this._snapGridY=chY1-sH;
                    //this._snapSetLocation(sX1 , this._snapGridY);
                    var tmpX = chX1 + Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) * this._snapGridSize;
                    this._snapSetLocation(tmpX , chY1-sH);
                }
                else {
                    this._snapSetLocation(sX1, chY1-sH);
                }
            }
            else if (C4a || C4b){
                if (this.isGridSnapEnabled){
                    //if(!this._snapGridX) this._snapGridX=chX2;
                    //this._snapSetLocation(this._snapGridX, sY1);
                    var tmpY = chY1 + Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) * this._snapGridSize;
                    this._snapSetLocation(chX2, tmpY);
                }
                else {
                    this._snapSetLocation(chX2, sY1);
                }
            }
            else if (C6a || C6b){
                if (this.isGridSnapEnabled){
                    //if(!this._snapGridY) this._snapGridY=chY2;
                    //this._snapSetLocation(sX1 , this._snapGridY);
                    var tmpX = chX1 + Math.floor( ( sX1 - chX1 ) / this._snapGridSize ) * this._snapGridSize;
                    this._snapSetLocation(tmpX , chY2);
                }
                else {
                    this._snapSetLocation(sX1, chY2);
                }
            }
            else if (C8a || C8b){
                if (this.isGridSnapEnabled){
                    //if(!this._snapGridX) this._snapGridX=chX1-sW;
                    //this._snapSetLocation(chX1-sW , sY1);
                    var tmpY = chY1 + Math.floor( ( sY1 - chY1 ) / this._snapGridSize ) * this._snapGridSize;
                    this._snapSetLocation(chX1-sW, tmpY);
                }
                else {
                    this._snapSetLocation(chX1-sW, sY1);
                }
            }

            if (C1 || C2a || C2b || C3 || C4a || C4b || C5 || C6a || C6b || C7 || C8a || C8b){
                this._snapObject=ch;
                this.invokeEvent("snap");
                ch._snapObject=this;
                ch.invokeEvent("snap");
            }
        }
    }
};
